<?php

namespace App\Services;

use App\Imports\AtkItemRowsImport;
use App\Models\AtkCategory;
use App\Models\AtkItem;
use App\Models\AtkUnit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;

class AtkItemImportService
{
    public function import(string|UploadedFile|TemporaryUploadedFile $path, User $actor): array
    {
        $resolvedPath = $this->resolveImportPath($path);

        $reader = new AtkItemRowsImport;
        Excel::import($reader, $resolvedPath);
        $rows = $reader->rows ?? collect();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'File impor tidak memiliki baris data.']);
        }

        $normalizedRows = $rows
            ->filter(fn ($row): bool => collect($row)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty())
            ->values()
            ->map(function ($row): array {
                $row = collect($row)
                    ->map(fn ($value) => is_string($value) ? trim($value) : $value)
                    ->all();

                if (array_key_exists('item_name', $row)) {
                    $row['name'] = $row['item_name'];
                }

                if (isset($row['size']) && is_numeric($row['size'])) {
                    $row['size'] = (string) $row['size'];
                }

                if (isset($row['unit']) && strcasecmp((string) $row['unit'], 'Rim') === 0) {
                    $row['unit'] = 'Ream';
                }

                if (array_key_exists('quantity', $row)) {
                    $row['current_stock'] = $this->normalizeQuantity($row['quantity'], $row);
                }

                if (array_key_exists('actual', $row)) {
                    $row['actual_stock'] = $this->normalizeQuantity($row['actual'], $row);
                }

                return $row;
            });

        $this->validateRows($normalizedRows);

        return DB::transaction(function () use ($normalizedRows, $actor): array {
            $created = 0;
            $updated = 0;
            $stockAdjusted = 0;

            foreach ($normalizedRows as $row) {
                $item = $this->findItem($row);
                $isNew = $item === null;
                $unit = AtkUnit::query()
                    ->whereRaw('LOWER(name) = ?', [strtolower($row['unit'])])
                    ->first() ?? AtkUnit::query()->create([
                        'name' => $row['unit'],
                        'is_active' => true,
                    ]);
                $attributes = [
                    'name' => $row['name'],
                    'unit' => $unit->name,
                    'atk_unit_id' => $unit->id,
                ];

                if (array_key_exists('category', $row)) {
                    $category = filled($row['category'])
                        ? (AtkCategory::query()
                            ->whereRaw('LOWER(name) = ?', [strtolower($row['category'])])
                            ->first() ?? AtkCategory::query()->create([
                                'name' => $row['category'],
                                'is_active' => true,
                            ]))
                        : null;
                    $attributes['category'] = $category?->name;
                    $attributes['atk_category_id'] = $category?->id;
                }

                if (array_key_exists('minimum_stock', $row)) {
                    $attributes['minimum_stock'] = $row['minimum_stock'] === '' ? null : $row['minimum_stock'];
                }

                if (array_key_exists('is_active', $row)) {
                    $attributes['is_active'] = $this->toBoolean($row['is_active']);
                }

                if (array_key_exists('size', $row)) {
                    $attributes['size'] = $row['size'] === '' ? null : $row['size'];
                }

                if (array_key_exists('actual_stock', $row)) {
                    $attributes['actual_stock'] = $row['actual_stock'] === '' ? null : $row['actual_stock'];
                }

                if ($isNew) {
                    $item = AtkItem::query()->create([
                        'code' => filled($row['code'] ?? null) ? $row['code'] : $this->nextCode(),
                        ...$attributes,
                        'current_stock' => 0,
                    ]);
                    $created++;
                } else {
                    $item->update($attributes);
                    $updated++;
                }

                if (! array_key_exists('current_stock', $row) || $row['current_stock'] === null || $row['current_stock'] === '') {
                    continue;
                }

                $targetStock = round((float) $row['current_stock'], 2);
                $difference = $targetStock - (float) $item->current_stock;

                if ($difference === 0.0) {
                    continue;
                }

                $warehouse = app(AtkWarehouseStockService::class);
                if ($difference > 0) {
                    $warehouse->incoming($item, $difference, $actor, 'Impor stok ATK');
                } else {
                    $warehouse->adjust($item, $difference, $actor, 'Penyesuaian dari impor stok ATK');
                }
                $stockAdjusted++;
            }

            return compact('created', 'updated', 'stockAdjusted');
        });
    }

    private function resolveImportPath(string|UploadedFile|TemporaryUploadedFile $path): string
    {
        if ($path instanceof UploadedFile && method_exists($path, 'getRealPath')) {
            $realPath = $path->getRealPath();

            if (is_string($realPath) && file_exists($realPath)) {
                return $realPath;
            }
        }

        if (is_string($path) && ! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $storagePath = Storage::disk('local')->path($path);

            if (file_exists($storagePath)) {
                return $storagePath;
            }
        }

        return is_string($path) ? $path : $path->getPathname();
    }

    private function validateRows(Collection $rows): void
    {
        $errors = [];
        $codes = [];

        foreach ($rows as $index => $row) {
            $validator = Validator::make($row, [
                'code' => ['nullable', 'string', 'max:50'],
                'name' => ['required', 'string', 'max:255'],
                'size' => ['nullable', 'string', 'max:100'],
                'category' => ['nullable', 'string', 'max:100'],
                'unit' => ['required', 'string', 'max:50'],
                'minimum_stock' => ['nullable', 'numeric', 'min:0'],
                'current_stock' => ['nullable', 'numeric', 'min:0'],
                'actual_stock' => ['nullable', 'numeric', 'min:0'],
                'is_active' => ['nullable'],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors['file.row_'.($index + 2)][] = $message;
                }
            }

            $code = filled($row['code'] ?? null) ? $row['code'] : null;
            if ($code && in_array($code, $codes, true)) {
                $errors['file.row_'.($index + 2)][] = "Kode {$code} muncul lebih dari satu kali.";
            }
            $codes[] = $code;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function toBoolean(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'ya'], true);
    }

    private function findItem(array $row): ?AtkItem
    {
        $query = AtkItem::query()->lockForUpdate();

        if (filled($row['code'] ?? null)) {
            return $query->where('code', $row['code'])->first();
        }

        $query
            ->whereRaw('LOWER(name) = ?', [strtolower($row['name'])])
            ->whereRaw('LOWER(unit) = ?', [strtolower($row['unit'])]);

        if (array_key_exists('size', $row)) {
            $row['size'] === '' || $row['size'] === null
                ? $query->whereNull('size')
                : $query->where('size', $row['size']);
        }

        return $query->first();
    }

    private function normalizeQuantity(mixed $value, array &$row): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d+(?:\.\d+)?(?:\s+\d+\/\d+|\/\d+)?)\s+([\p{L}]+)$/u', $value, $matches)) {
            $row['unit'] = strcasecmp($matches[2], 'Rim') === 0 ? 'Ream' : $matches[2];

            return $this->parseNumericQuantity($matches[1]);
        }

        return $this->parseNumericQuantity($value);
    }

    private function parseNumericQuantity(string $value): mixed
    {
        if (preg_match('/^(\d+(?:\.\d+)?)\/(\d+(?:\.\d+)?)$/', $value, $matches)) {
            $denominator = (float) $matches[2];

            return $denominator === 0.0 ? $value : (float) $matches[1] / $denominator;
        }

        if (preg_match('/^(\d+)\s+(\d+)\/(\d+)$/', $value, $matches)) {
            $denominator = (float) $matches[3];

            return $denominator === 0.0
                ? $value
                : (float) $matches[1] + (float) $matches[2] / $denominator;
        }

        if ($value === '-') {
            return null;
        }

        return $value;
    }

    private function nextCode(): string
    {
        $sequence = AtkItem::query()->count() + 1;

        do {
            $code = 'ATK-'.str_pad((string) $sequence++, 5, '0', STR_PAD_LEFT);
        } while (AtkItem::query()->where('code', $code)->exists());

        return $code;
    }
}
