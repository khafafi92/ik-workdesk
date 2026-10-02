<?php

namespace App\Services;

use App\Models\AtkItem;
use App\Models\AtkRequestHistory;
use App\Models\AtkRequestItem;
use App\Models\AtkStockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AtkWarehouseStockService
{
    public function markReady(AtkRequestItem $requestItem, User $actor, ?string $note = null): AtkRequestItem
    {
        return $this->setItemStatus($requestItem, 'ready', $actor, $note);
    }

    public function markWaitingProcurement(AtkRequestItem $requestItem, User $actor, ?string $note = null): AtkRequestItem
    {
        return $this->setItemStatus($requestItem, 'waiting_procurement', $actor, $note);
    }

    public function incoming(AtkItem $item, float $quantity, User $actor, ?string $description = null): AtkStockMovement
    {
        return $this->move($item, $quantity, 'incoming', $actor, $description);
    }

    public function adjust(AtkItem $item, float $quantity, User $actor, ?string $description = null): AtkStockMovement
    {
        return $this->move($item, $quantity, 'adjustment', $actor, $description);
    }

    public function issue(AtkRequestItem $requestItem, float $quantity, User $actor): AtkRequestItem
    {
        $quantity = round($quantity, 2);

        $issuedRequestItem = DB::transaction(function () use ($requestItem, $quantity, $actor): AtkRequestItem {
            $requestItem = AtkRequestItem::query()->with('request')->lockForUpdate()->findOrFail($requestItem->id);
            $item = AtkItem::query()->lockForUpdate()->findOrFail($requestItem->atk_item_id);
            if ($quantity <= 0 || $quantity > $requestItem->outstandingRequested()) {
                throw ValidationException::withMessages(['qty' => 'Jumlah issue harus lebih dari nol dan tidak boleh melebihi sisa permintaan.']);
            }

            if ((float) $item->current_stock < $quantity) {
                throw ValidationException::withMessages(['qty' => 'Stok gudang tidak mencukupi untuk issue ini.']);
            }

            $before = (float) $item->current_stock;
            $after = $before - $quantity;
            $item->update(['current_stock' => $after]);
            $requestItem->update([
                'qty_issued' => (float) $requestItem->qty_issued + $quantity,
                'status' => 'issued',
                'issued_at' => now(),
                'issued_by' => $actor->id,
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
                'meta' => ['qty' => $quantity],
                'performed_by' => $actor->id,
            ]);
            app(AtkRequestStatusService::class)->recalculate($requestItem->request->fresh('items'));

            return $requestItem->fresh();
        });

        app(AtkRequestNotificationService::class)->notifyRequesterOfIssuedItem($issuedRequestItem, $quantity);

        return $issuedRequestItem;
    }

    private function move(AtkItem $item, float $quantity, string $type, User $actor, ?string $description): AtkStockMovement
    {
        return DB::transaction(function () use ($item, $quantity, $type, $actor, $description): AtkStockMovement {
            $item = AtkItem::query()->lockForUpdate()->findOrFail($item->id);
            $quantity = round($quantity, 2);

            if ($quantity == 0 || ($type === 'incoming' && $quantity < 0)) {
                throw ValidationException::withMessages(['qty' => 'Jumlah stok harus valid.']);
            }

            $before = (float) $item->current_stock;
            $after = $before + $quantity;

            if ($after < 0) {
                throw ValidationException::withMessages(['qty' => 'Penyesuaian tidak boleh membuat stok gudang negatif.']);
            }

            $item->update(['current_stock' => $after]);

            return AtkStockMovement::create([
                'atk_item_id' => $item->id,
                'movement_type' => $type,
                'qty' => $quantity,
                'balance_before' => $before,
                'balance_after' => $after,
                'description' => $description,
                'created_by' => $actor->id,
            ]);
        });
    }

    private function setItemStatus(AtkRequestItem $requestItem, string $status, User $actor, ?string $note): AtkRequestItem
    {
        return DB::transaction(function () use ($requestItem, $status, $actor, $note): AtkRequestItem {
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
        });
    }
}
