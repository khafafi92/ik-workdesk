<?php

namespace App\Services;

use App\Models\AtkItem;
use App\Models\AtkRequest;
use App\Models\AtkRequestHistory;
use App\Models\AtkRequestItem;
use App\Models\AtkStockMovement;
use App\Models\InventoryWarehouse;
use App\Models\User;
use App\Services\Inventory\InventoryAccess;
use App\Services\Inventory\InventoryStock;
use App\Services\Inventory\Warehouses;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AtkWarehouseStockService
{
    public function markReady(AtkRequestItem $requestItem, User $actor, ?string $note = null): AtkRequestItem
    {
        $this->authorize($actor);

        return $this->setItemStatus($requestItem, 'ready', $actor, $note);
    }

    public function markWaitingProcurement(AtkRequestItem $requestItem, User $actor, ?string $note = null): AtkRequestItem
    {
        $this->authorize($actor);

        return $this->setItemStatus($requestItem, 'waiting_procurement', $actor, $note);
    }

    public function incoming(AtkItem $item, float $quantity, User $actor, ?string $description = null, ?InventoryWarehouse $warehouse = null): AtkStockMovement
    {
        return $this->move($item, $quantity, 'incoming', $actor, $description, $warehouse);
    }

    public function adjust(AtkItem $item, float $quantity, User $actor, ?string $description = null, ?InventoryWarehouse $warehouse = null): AtkStockMovement
    {
        return $this->move($item, $quantity, 'adjustment', $actor, $description, $warehouse);
    }

    public function issue(AtkRequestItem $requestItem, float $quantity, User $actor, ?string $note = null): AtkRequestItem
    {
        $this->authorize($actor);
        $quantity = round($quantity, 2);

        $issuedRequestItem = DB::transaction(function () use ($requestItem, $quantity, $actor, $note): AtkRequestItem {
            $request = AtkRequest::query()->lockForUpdate()->findOrFail($requestItem->atk_request_id);
            $requestItem = AtkRequestItem::query()->with('request')->lockForUpdate()->findOrFail($requestItem->id);
            $item = AtkItem::query()->lockForUpdate()->findOrFail($requestItem->atk_item_id);
            if (in_array($request->status, ['cancelled', 'completed', 'draft'], true) || $requestItem->status === 'cancelled') {
                throw ValidationException::withMessages(['qty' => 'Barang hanya dapat diserahkan untuk permintaan aktif.']);
            }
            if (! is_finite($quantity) || $quantity <= 0 || $quantity > $requestItem->outstandingRequested()) {
                throw ValidationException::withMessages(['qty' => 'Jumlah issue harus lebih dari nol dan tidak boleh melebihi sisa permintaan.']);
            }

            $stock = app(InventoryStock::class);
            $warehouse = $stock->atkWarehouse($request);
            if ($stock->availableAtk($item, $warehouse->id) < $quantity) {
                if ($stock->availableAtk($item, app(Warehouses::class)->legacyAtk()->id) > 0) {
                    throw ValidationException::withMessages(['qty' => 'Petakan saldo ATK lama ke KPMOG/APCA di Inventori sebelum menyerahkan barang.']);
                }
                throw ValidationException::withMessages(['qty' => 'Stok gudang tidak mencukupi untuk issue ini.']);
            }

            $before = (float) $item->current_stock;
            $after = $before - $quantity;
            $stock->issueAtk($item, $warehouse, $quantity, $actor, $requestItem->id);
            $requestItem->update([
                'qty_issued' => (float) $requestItem->qty_issued + $quantity,
                'status' => 'issued',
                'issued_at' => now(),
                'issued_by' => $actor->id,
                'ga_note' => $note ?? $requestItem->ga_note,
            ]);
            AtkStockMovement::create([
                'atk_item_id' => $item->id,
                'movement_type' => 'outgoing',
                'qty' => -$quantity,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => AtkRequestItem::class,
                'reference_id' => $requestItem->id,
                'description' => "Issued for {$requestItem->request->request_number}.",
                'created_by' => $actor->id,
            ]);
            AtkRequestHistory::create([
                'atk_request_id' => $requestItem->atk_request_id,
                'atk_request_item_id' => $requestItem->id,
                'action' => 'item_issued',
                'description' => "Issued {$quantity} {$requestItem->unit}.",
                'meta' => ['qty' => $quantity, 'note' => $note],
                'performed_by' => $actor->id,
            ]);
            app(AtkRequestStatusService::class)->recalculate($requestItem->request->fresh('items'));

            return $requestItem->fresh();
        }, 3);

        app(AtkRequestNotificationService::class)->notifyRequesterOfIssuedItem($issuedRequestItem, $quantity);

        return $issuedRequestItem;
    }

    private function move(AtkItem $item, float $quantity, string $type, User $actor, ?string $description, ?InventoryWarehouse $warehouse = null): AtkStockMovement
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($item, $quantity, $type, $actor, $description, $warehouse): AtkStockMovement {
            $item = AtkItem::query()->lockForUpdate()->findOrFail($item->id);
            $quantity = round($quantity, 2);

            if (! is_finite($quantity) || $quantity == 0 || ($type === 'incoming' && $quantity < 0)) {
                throw ValidationException::withMessages(['qty' => 'Jumlah stok harus valid.']);
            }

            $before = (float) $item->current_stock;
            $after = $before + $quantity;

            if ($after < 0) {
                throw ValidationException::withMessages(['qty' => 'Penyesuaian tidak boleh membuat stok gudang negatif.']);
            }

            $stock = app(InventoryStock::class);
            if ($warehouse && ! $warehouse->legacy_atk) {
                if (! $warehouse->is_active || ($warehouse->kind === 'PROJECT' && ! $warehouse->project?->is_active) || ! in_array($warehouse->company, ['KPMOG', 'APCA'], true)) {
                    throw ValidationException::withMessages(['warehouse' => 'Pilih gudang entitas yang aktif.']);
                }
                $balance = $stock->balance($warehouse, $item->inventoryItem, null);
                $stock->move($balance, (int) round($quantity * 1000), $type, (string) Str::uuid(), $actor, $description ?? 'Mutasi stok ATK.');
            } else {
                $item->update(['current_stock' => $after]);
            }
            $movement = AtkStockMovement::create([
                'atk_item_id' => $item->id,
                'movement_type' => $type,
                'qty' => $quantity,
                'balance_before' => $before,
                'balance_after' => $after,
                'description' => $description,
                'created_by' => $actor->id,
            ]);
            if (! $warehouse || $warehouse->legacy_atk) {
                $stock->syncLegacyAtk($item, $before, $after, $actor, 'atk-movement-'.$movement->id);
            }

            return $movement;
        });
    }

    private function setItemStatus(AtkRequestItem $requestItem, string $status, User $actor, ?string $note): AtkRequestItem
    {
        return DB::transaction(function () use ($requestItem, $status, $actor, $note): AtkRequestItem {
            AtkRequest::query()->lockForUpdate()->findOrFail($requestItem->atk_request_id);
            $requestItem = AtkRequestItem::query()->with('request')->lockForUpdate()->findOrFail($requestItem->id);
            $requestItem->update([
                'status' => $status,
                'ga_note' => $note,
                $status === 'ready' ? 'ready_at' : 'waiting_procurement_at' => now(),
            ]);
            AtkRequestHistory::create([
                'atk_request_id' => $requestItem->atk_request_id,
                'atk_request_item_id' => $requestItem->id,
                'action' => $status,
                'description' => $note,
                'performed_by' => $actor->id,
            ]);
            app(AtkRequestStatusService::class)->recalculate($requestItem->request->fresh('items'));

            return $requestItem->fresh();
        }, 3);
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->isActiveForAccess() && ($actor->hasPermission('atk.manage') || InventoryAccess::administer($actor)), 403);
    }
}
