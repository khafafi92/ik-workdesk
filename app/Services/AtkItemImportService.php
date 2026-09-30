<?php

namespace App\Services;

use App\Imports\AtkItemRowsImport;
use App\Models\AtkCategory;
use App\Models\AtkItem;
use App\Models\AtkUnit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class AtkItemImportService
{
    public function import(string $path, User $actor): array
    {
        $reader = new AtkItemRowsImport;
        Excel::import($reader, $path, 'local');
        $rows = $reader->rows ?? collect();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'File impor tidak memiliki baris data.']);
        }

        $normalizedRows = $rows
            ->filter(fn ($row): bool => collect($row)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty())
            ->values()
            ->map(fn ($row): array => collect($row)->map(fn ($value) => is_string($value) ? trim($value) : $value)->all());

        $this->validateRows($normalizedRows);

        return DB::transaction(function () use ($normalizedRows, $actor): array {
            $created = 0;
            $updated = 0;
            $stockAdjusted = 0;

            foreach ($normalizedRows as $row) {
                $item = AtkItem::query()->where('code', $row['code'])->lockForUpdate()->first();
                $isNew = $item === null;
                $unit = AtkUnit::query()
                    ->whereRaw('LOWER(name) = ?', [strtolower($row['unit'])])
                    ->first() ?? AtkUnit::query()->create([
                        'name' => $row['unit'],
                        'is_active' => true,
                    ]);
                $category = filled($row['category'] ?? null)
                    ? (AtkCategory::query()
                        ->whereRaw('LOWER(name) = ?', [strtolower($row['category'])])
                        ->first() ?? AtkCategory::query()->create([
                            'name' => $row['category'],
                            'is_active' => true,
                        ]))
                    : null;

                $attributes = [
                    'name' => $row['name'],
                    'category' => $category?->name,
                    'atk_category_id' => $category?->id,
                    'unit' => $unit->name,
                    'atk_unit_id' => $unit->id,
                    'minimum_stock' => $row['minimum_stock'] ?? null,
                    'is_active' => $this->toBoolean($row['is_active'] ?? true),
                ];

                if ($isNew) {
                    $item = AtkItem::query()->create([
                        'code' => $row['code'],
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

    private function validateRows(Collection $rows): void
    {
        $errors = [];
        $codes = [];

        foreach ($rows as $index => $row) {
            $validator = Validator::make($row, [
                'code' => ['required', 'string', 'max:50'],
                'name' => ['required', 'string', 'max:255'],
                'category' => ['nullable', 'string', 'max:100'],
                'unit' => ['required', 'string', 'max:50'],
                'minimum_stock' => ['nullable', 'numeric', 'min:0'],
                'current_stock' => ['nullable', 'numeric', 'min:0'],
                'is_active' => ['nullable'],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors['file.row_'.($index + 2)][] = $message;
                }
            }

            $code = $row['code'] ?? null;
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
}
