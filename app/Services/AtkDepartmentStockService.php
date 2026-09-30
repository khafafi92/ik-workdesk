<?php

namespace App\Services;

use App\Models\AtkDepartmentBalance;
use App\Models\AtkDepartmentStockMovement;
use App\Models\AtkRequestHistory;
use App\Models\AtkRequestItem;
use App\Models\AtkUsageTransaction;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AtkDepartmentStockService
{
    public function receive(AtkRequestItem $requestItem, User $actor): AtkRequestItem
    {
        return DB::transaction(function () use ($requestItem, $actor): AtkRequestItem {
            $requestItem = AtkRequestItem::query()->with('request')->lockForUpdate()->findOrFail($requestItem->id);
            $quantity = $requestItem->awaitingReceipt();

            if ($quantity <= 0) {
                return $requestItem;
            }

            $balance = $this->lockedBalance($requestItem->request->department_id, $requestItem->atk_item_id);
            $before = (float) $balance->qty_available;
            $after = $before + $quantity;
            $balance->update(['qty_available' => $after, 'last_received_at' => now()]);
            $requestItem->update([
                'qty_received' => (float) $requestItem->qty_received + $quantity,
                'status' => (float) $requestItem->qty_received + $quantity >= (float) $requestItem->qty_requested ? 'received' : 'issued',
                'received_at' => now(),
                'received_by' => $actor->id,
            ]);
            AtkDepartmentStockMovement::create([
                'department_id' => $requestItem->request->department_id,
                'atk_item_id' => $requestItem->atk_item_id,
                'movement_type' => 'received',
                'qty' => $quantity,
                'balance_before' => $before,
                'balance_after' => $after,
                'atk_request_id' => $requestItem->atk_request_id,
                'atk_request_item_id' => $requestItem->id,
                'description' => "Received from {$requestItem->request->request_number}.",
                'performed_by' => $actor->id,
            ]);
            AtkRequestHistory::create([
                'atk_request_id' => $requestItem->atk_request_id,
                'atk_request_item_id' => $requestItem->id,
                'action' => 'item_received',
                'description' => "Confirmed received {$quantity} {$requestItem->unit}.",
                'meta' => ['qty' => $quantity],
                'performed_by' => $actor->id,
            ]);
            app(AtkRequestStatusService::class)->recalculate($requestItem->request->fresh('items'));

            return $requestItem->fresh();
        });
    }

    public function use(Department $department, int $itemId, float $quantity, User $actor, ?string $purpose = null, ?string $note = null, mixed $usageDate = null): AtkUsageTransaction
    {
        return DB::transaction(function () use ($department, $itemId, $quantity, $actor, $purpose, $note, $usageDate): AtkUsageTransaction {
            $balance = $this->lockedBalance($department->id, $itemId);
            $quantity = round($quantity, 2);

            if ($quantity <= 0 || $quantity > (float) $balance->qty_available) {
                throw ValidationException::withMessages(['qty_used' => 'Jumlah penggunaan harus lebih dari nol dan tidak boleh melebihi saldo department.']);
            }

            $before = (float) $balance->qty_available;
            $after = $before - $quantity;
            $balance->update(['qty_available' => $after, 'last_used_at' => now()]);
            $usage = AtkUsageTransaction::create([
                'department_id' => $department->id,
                'atk_item_id' => $itemId,
                'qty_used' => $quantity,
                'usage_date' => $usageDate ?? today(),
                'purpose' => $purpose,
                'note' => $note,
                'used_by' => $actor->id,
            ]);
            AtkDepartmentStockMovement::create([
                'department_id' => $department->id,
                'atk_item_id' => $itemId,
                'movement_type' => 'used',
                'qty' => -$quantity,
                'balance_before' => $before,
                'balance_after' => $after,
                'description' => $purpose,
                'performed_by' => $actor->id,
            ]);

            return $usage;
        });
    }

    private function lockedBalance(int $departmentId, int $itemId): AtkDepartmentBalance
    {
        AtkDepartmentBalance::query()->firstOrCreate([
            'department_id' => $departmentId,
            'atk_item_id' => $itemId,
        ]);

        return AtkDepartmentBalance::query()
            ->where('department_id', $departmentId)
            ->where('atk_item_id', $itemId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
