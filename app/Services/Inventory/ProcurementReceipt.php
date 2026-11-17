<?php

namespace App\Services\Inventory;

use App\Models\AtkItem;
use App\Models\AtkStockMovement;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryReceipt;
use App\Models\InventoryWarehouse;
use App\Models\Scm\Procurement;
use App\Models\User;
use App\Services\Scm\Decimal;
use App\Services\Scm\Workflow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProcurementReceipt
{
    public function receive(User $actor, int $requestId, array $data): InventoryReceipt
    {
        $data = Validator::make($data, ['token' => 'required|uuid', 'warehouse_id' => 'required|integer|exists:inventory_warehouses,id',
            'received_on' => 'required|date|before_or_equal:today', 'reference' => 'required|string|max:255', 'notes' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1|max:100', 'lines.*.request_item_id' => 'required|integer|distinct',
            'lines.*.inventory_item_id' => 'required|integer|exists:inventory_items,id', 'lines.*.quantity' => 'required'])->validate();

        return DB::transaction(function () use ($actor, $requestId, $data): InventoryReceipt {
            $request = Procurement::lockForUpdate()->findOrFail($requestId);
            abort_unless(InventoryAccess::administer($actor), 403);
            if ($existing = InventoryReceipt::where('token', $data['token'])->first()) {
                $requestedLines = collect($data['lines'])->map(fn ($line) => [
                    'request_item_id' => (int) $line['request_item_id'],
                    'inventory_item_id' => (int) $line['inventory_item_id'],
                    'quantity_milli' => Decimal::parse($line['quantity'], 3),
                ])->sortBy('request_item_id')->values()->all();
                $savedLines = $existing->movements()->with('balance')->get()->map(fn ($movement) => [
                    'request_item_id' => (int) $movement->request_item_id,
                    'inventory_item_id' => (int) $movement->balance->inventory_item_id,
                    'quantity_milli' => (int) $movement->quantity_milli,
                ])->sortBy('request_item_id')->values()->all();
                $same = (int) $existing->request_id === (int) $request->id
                    && (int) $existing->warehouse_id === (int) $data['warehouse_id']
                    && (int) $existing->created_by === (int) $actor->id
                    && $existing->received_on->toDateString() === Carbon::parse($data['received_on'])->toDateString()
                    && $existing->reference === $data['reference']
                    && $existing->notes === ($data['notes'] ?? null)
                    && $savedLines === $requestedLines;
                if (! $same) {
                    throw ValidationException::withMessages(['receipt' => 'Penerimaan ini sudah disimpan dengan data berbeda. Muat ulang dan periksa riwayat penerimaan.']);
                }

                return $existing;
            }
            if ($request->kind !== 'PR' || ! in_array($request->status, ['selected', 'legal', 'completed'], true) || $request->cbe?->status !== 'final' || ! $request->cbe->selected_vendor_id) {
                throw ValidationException::withMessages(['receipt' => 'Penerimaan barang memerlukan PR dengan CBE final dan vendor terpilih.']);
            }
            $order = $request->purchaseOrder()->lockForUpdate()->first();
            if (! $order || (int) $order->cbe_id !== (int) $request->cbe->id) {
                throw ValidationException::withMessages(['receipt' => 'Penerimaan barang memerlukan PO untuk PR ini.']);
            }
            $warehouse = InventoryWarehouse::findOrFail($data['warehouse_id']);
            if (! InventoryAccess::warehouseMatches($warehouse, $request->company, $request->need_type, $request->project_numbering_id)) {
                throw ValidationException::withMessages(['warehouse_id' => 'Gudang tujuan harus sesuai entitas dan HO/Project pada PR.']);
            }
            $receipt = InventoryReceipt::create(['token' => $data['token'], 'request_id' => $request->id, 'purchase_order_id' => $order->id, 'warehouse_id' => $warehouse->id,
                'received_on' => $data['received_on'], 'reference' => $data['reference'], 'notes' => $data['notes'] ?? null, 'created_by' => $actor->id]);
            $stock = app(InventoryStock::class);
            foreach (collect($data['lines'])->sortBy('inventory_item_id') as $line) {
                $source = $request->items()->lockForUpdate()->findOrFail($line['request_item_id']);
                $atk = AtkItem::where('inventory_item_id', $line['inventory_item_id'])->lockForUpdate()->first();
                $item = InventoryItem::lockForUpdate()->findOrFail($line['inventory_item_id']);
                $quantity = Decimal::parse($line['quantity'], 3);
                $received = (int) InventoryMovement::where('request_item_id', $source->id)->where('type', 'pr_receipt')->sum('quantity_milli');
                if (! $item->is_active || $quantity <= 0 || $quantity + $received > $source->quantity_milli
                    || ($item->kind === 'atk' && $quantity % 10 !== 0) || ($item->kind === 'device' && $quantity % 1000 !== 0)) {
                    throw ValidationException::withMessages(['receipt' => 'Periksa jumlah diterima. Jumlah tidak boleh melebihi sisa PR; ATK maksimal dua desimal dan perangkat harus utuh.']);
                }
                if (($source->inventory_item_id && (int) $source->inventory_item_id !== $item->id) || strcasecmp(trim($source->unit), trim($item->unit)) !== 0) {
                    throw ValidationException::withMessages(['receipt' => 'Master barang dan satuan penerimaan harus sesuai baris PR.']);
                }
                if (! $source->inventory_item_id) {
                    $source->update(['inventory_item_id' => $item->id]);
                }
                $beforeAtk = $atk?->current_stock;
                $balance = $stock->balance($warehouse, $item, $request->department_id);
                $stock->move($balance, $quantity, 'pr_receipt', $data['token'].'-'.$source->id, $actor, $data['notes'] ?? $data['reference'],
                    ['receipt_id' => $receipt->id, 'request_item_id' => $source->id]);
                if ($atk) {
                    AtkStockMovement::create(['atk_item_id' => $atk->id, 'movement_type' => 'incoming', 'qty' => $quantity / 1000,
                        'balance_before' => $beforeAtk, 'balance_after' => $atk->fresh()->current_stock,
                        'reference_type' => InventoryReceipt::class, 'reference_id' => $receipt->id,
                        'description' => 'Penerimaan '.$request->number.' ke '.$warehouse->label(), 'created_by' => $actor->id]);
                }
            }
            app(Workflow::class)->audit($actor, $request, 'penerimaan_gudang', ['receipt_id' => $receipt->id,
                'warehouse_id' => $warehouse->id, 'reference' => $receipt->reference], $request->id);

            return $receipt;
        }, 3);
    }
}
