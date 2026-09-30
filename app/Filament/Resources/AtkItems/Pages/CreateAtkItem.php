<?php

namespace App\Filament\Resources\AtkItems\Pages;

use App\Filament\Resources\AtkItems\AtkItemResource;
use App\Models\AtkCategory;
use App\Models\AtkUnit;
use App\Services\AtkWarehouseStockService;
use Filament\Resources\Pages\CreateRecord;

class CreateAtkItem extends CreateRecord
{
    protected static string $resource = AtkItemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->syncMasterLabels($data);
    }

    protected function afterCreate(): void
    {
        $quantity = (float) $this->record->current_stock;

        if ($quantity <= 0) {
            return;
        }

        $this->record->update(['current_stock' => 0]);
        app(AtkWarehouseStockService::class)->incoming(
            $this->record,
            $quantity,
            auth()->user(),
            'Stok awal master barang',
        );
    }

    private function syncMasterLabels(array $data): array
    {
        $data['category'] = filled($data['atk_category_id'] ?? null)
            ? AtkCategory::query()->findOrFail($data['atk_category_id'])->name
            : null;
        $data['unit'] = AtkUnit::query()->findOrFail($data['atk_unit_id'])->name;

        return $data;
    }
}
