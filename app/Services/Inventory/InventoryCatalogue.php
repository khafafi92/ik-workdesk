<?php

namespace App\Services\Inventory;

use App\Models\AtkCategory;
use App\Models\AtkItem;
use App\Models\AtkUnit;
use App\Models\InventoryItem;
use App\Models\ItDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryCatalogue
{
    public static function installed(): bool
    {
        return Schema::hasTable('inventory_items');
    }

    public function save(User $actor, array $data, ?int $id = null, ?int $version = null): InventoryItem
    {
        $data = Validator::make($data, ['code' => ['required', 'string', 'max:80', Rule::unique('inventory_items')->ignore($id)],
            'name' => 'required|string|max:255', 'kind' => ['required', Rule::in(['atk', 'material', 'device'])],
            'unit' => 'required|string|max:50', 'category' => 'nullable|string|max:255', 'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255', 'size' => 'nullable|string|max:100', 'specification' => 'nullable|string|max:10000', 'is_active' => 'required|boolean'])->validate();
        abort_unless(InventoryAccess::editItem($actor, $data['kind']), 403);

        return DB::transaction(function () use ($actor, $data, $id, $version): InventoryItem {
            $item = $id ? InventoryItem::lockForUpdate()->findOrFail($id) : new InventoryItem;
            if ($id) {
                abort_unless(InventoryAccess::editItem($actor, $item->kind), 403);
                if ($item->version !== $version || $item->kind !== $data['kind']) {
                    throw ValidationException::withMessages(['catalogue' => 'Data berubah atau jenis barang berbeda. Muat ulang master barang.']);
                }
                if ($item->unit !== $data['unit'] && (DB::table('scm_request_items')->where('inventory_item_id', $id)->exists()
                    || DB::table('inventory_movements')->whereIn('balance_id', DB::table('inventory_balances')->where('inventory_item_id', $id)->select('id'))->exists()
                    || $item->atkItem?->requestItems()->exists())) {
                    throw ValidationException::withMessages(['catalogue' => 'Satuan barang yang sudah dipakai transaksi tidak dapat diubah. Buat master terpisah untuk satuan lain.']);
                }
                $data['version'] = $item->version + 1;
            }
            $item->fill($data)->save();
            if ($item->kind === 'atk') {
                $unit = AtkUnit::firstOrCreate(['name' => $item->unit], ['is_active' => true]);
                $category = filled($item->category) ? AtkCategory::firstOrCreate(['name' => $item->category], ['is_active' => true]) : null;
                $atk = $item->atkItem ?? new AtkItem(['code' => 'INV-'.$item->id, 'current_stock' => 0]);
                $atk->fill(['inventory_item_id' => $item->id, 'name' => $item->name, 'unit' => $item->unit,
                    'atk_unit_id' => $unit->id, 'category' => $category?->name, 'atk_category_id' => $category?->id,
                    'size' => $item->size, 'is_active' => $item->is_active])->saveQuietly();
            }

            return $item->refresh();
        }, 3);
    }

    public function syncAtk(AtkItem $atk): void
    {
        if (! self::installed()) {
            return;
        }
        $item = $atk->inventory_item_id ? InventoryItem::findOrFail($atk->inventory_item_id) : new InventoryItem(['code' => 'ATK-'.Str::ulid(), 'kind' => 'atk']);
        $item->fill(['name' => $atk->name, 'category' => $atk->category, 'unit' => $atk->unit, 'size' => $atk->size, 'is_active' => $atk->is_active ?? true]);
        if ($item->exists && $item->isDirty()) {
            $item->version++;
        }
        $item->save();
        if (! $atk->inventory_item_id) {
            $atk->forceFill(['inventory_item_id' => $item->id])->saveQuietly();
        }
    }

    public function linkDevice(ItDevice $device): void
    {
        if ($device->object_kind?->value === 'room' || ! self::installed()) {
            return;
        }
        if ($device->inventory_item_id) {
            if (! InventoryItem::whereKey($device->inventory_item_id)->where('kind', 'device')->exists()) {
                throw ValidationException::withMessages(['inventory_item_id' => 'Perangkat harus memakai master barang jenis Perangkat/aset.']);
            }

            return;
        }
        $item = InventoryItem::create(['code' => 'DEVICE-'.Str::ulid(), 'name' => trim($device->manufacturer.' '.$device->model_name) ?: $device->device_type->label(),
            'kind' => 'device', 'unit' => 'PCS', 'brand' => $device->manufacturer, 'model' => $device->model_name, 'is_active' => true]);
        $device->forceFill(['inventory_item_id' => $item->id])->saveQuietly();
    }
}
