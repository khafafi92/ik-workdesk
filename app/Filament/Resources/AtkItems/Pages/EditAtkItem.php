<?php

namespace App\Filament\Resources\AtkItems\Pages;

use App\Filament\Resources\AtkItems\AtkItemResource;
use App\Models\AtkCategory;
use App\Models\AtkUnit;
use Filament\Resources\Pages\EditRecord;

class EditAtkItem extends EditRecord
{
    protected static string $resource = AtkItemResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['category'] = filled($data['atk_category_id'] ?? null)
            ? AtkCategory::query()->findOrFail($data['atk_category_id'])->name
            : null;
        $data['unit'] = AtkUnit::query()->findOrFail($data['atk_unit_id'])->name;

        return $data;
    }
}
