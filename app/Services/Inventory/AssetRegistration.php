<?php

namespace App\Services\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\ItDevice;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssetRegistration
{
    public function consume(User $actor, ItDevice $device, int $balanceId): void
    {
        Gate::forUser($actor)->authorize('update', $device);
        if ($device->inventory_movement_id) {
            throw ValidationException::withMessages(['inventory_balance_id' => 'Unit ini sudah didaftarkan dari stok gudang.']);
        }
        $source = InventoryAccess::scopeBalances(InventoryBalance::query(), $actor)->findOrFail($balanceId);
        InventoryItem::whereKey($source->inventory_item_id)->lockForUpdate()->firstOrFail();
        $source = InventoryBalance::lockForUpdate()->findOrFail($source->id);
        if ($device->object_kind?->value === 'room' || $source->item->kind !== 'device' || $source->inventory_item_id !== (int) $device->inventory_item_id
            || ! $source->warehouse->is_active || ! $source->item->is_active || $source->quantity_milli < 1000
            || ! $actor->canAccessDepartment($source->department_id ?? 0)
            || ! str_contains($device->asset_number ?? '', '/'.$source->warehouse->company.'/')) {
            throw ValidationException::withMessages(['inventory_balance_id' => 'Stok, master barang, departemen, atau entitas aset tidak sesuai.']);
        }
        $movement = app(InventoryStock::class)->move($source, -1000, 'asset_registration', 'device-'.$device->id, $actor,
            'Pendaftaran unit aset '.$device->asset_number, ['reference_type' => 'it_device', 'reference_id' => $device->id]);
        $device->forceFill(['inventory_warehouse_id' => $source->warehouse_id, 'inventory_movement_id' => $movement->id])->saveQuietly();
    }
}
