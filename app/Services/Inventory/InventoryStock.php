<?php

namespace App\Services\Inventory;

use App\Models\AtkItem;
use App\Models\AtkRequest;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryWarehouse;
use App\Models\User;
use App\Services\Scm\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryStock
{
    public function balance(InventoryWarehouse $warehouse, InventoryItem $item, ?int $departmentId): InventoryBalance
    {
        $key = $warehouse->id.'-'.$item->id.'-'.($departmentId ?? 0);
        InventoryBalance::firstOrCreate(['balance_key' => $key], ['warehouse_id' => $warehouse->id, 'inventory_item_id' => $item->id,
            'department_id' => $departmentId, 'quantity_milli' => 0]);

        return InventoryBalance::where('balance_key', $key)->lockForUpdate()->firstOrFail();
    }

    public function move(InventoryBalance $balance, int $quantity, string $type, string $key, ?User $actor, string $notes, array $reference = []): InventoryMovement
    {
        if ($existing = InventoryMovement::where('transaction_key', $key)->first()) {
            abort_unless((int) $existing->balance_id === $balance->id, 409);

            return $existing;
        }
        $before = (int) $balance->quantity_milli;
        if ($before + $quantity < 0 || abs($quantity) > 999999999999999 || abs($before + $quantity) > 999999999999999) {
            throw ValidationException::withMessages(['stock' => 'Jumlah melampaui saldo tersedia atau batas pencatatan.']);
        }
        $balance->update(['quantity_milli' => $before + $quantity]);
        if (! in_array($type, ['opening', 'atk_sync'], true) && $balance->item->kind === 'atk') {
            AtkItem::where('inventory_item_id', $balance->inventory_item_id)->increment('current_stock', $quantity / 1000);
        }

        return InventoryMovement::create([...$reference, 'transaction_key' => $key, 'balance_id' => $balance->id, 'type' => $type,
            'quantity_milli' => $quantity, 'balance_before_milli' => $before, 'balance_after_milli' => $before + $quantity,
            'created_by' => $actor?->id, 'notes' => $notes]);
    }

    public function syncLegacyAtk(AtkItem $atk, float $before, float $after, User $actor, string $key): void
    {
        $warehouse = app(Warehouses::class)->legacyAtk();
        $item = $atk->inventoryItem()->firstOrFail();
        $balance = $this->balance($warehouse, $item, null);
        $other = (int) InventoryBalance::where('inventory_item_id', $item->id)->where('id', '!=', $balance->id)->sum('quantity_milli');
        $beforeMilli = Decimal::parse(number_format($before, 3, '.', ''), 3) - $other;
        $afterMilli = Decimal::parse(number_format($after, 3, '.', ''), 3) - $other;
        if (! InventoryMovement::where('balance_id', $balance->id)->exists() && $beforeMilli !== 0) {
            $this->move($balance, $beforeMilli, 'opening', 'opening-atk-'.$atk->id, null, 'Saldo awal Gudang Utama ATK.');
        }
        if ((int) $balance->quantity_milli !== $beforeMilli) {
            throw ValidationException::withMessages(['stock' => 'Saldo ATK dan inventori berbeda. Periksa mutasi sebelum melanjutkan.']);
        }
        $this->move($balance, $afterMilli - $beforeMilli, 'atk_sync', $key, $actor, 'Mutasi Gudang Utama melalui alur ATK.', ['reference_type' => 'atk_item', 'reference_id' => $atk->id]);
    }

    public function availableAtk(AtkItem $item, ?int $warehouseId): float
    {
        $warehouse = $warehouseId ? InventoryWarehouse::findOrFail($warehouseId) : app(Warehouses::class)->legacyAtk();
        $balances = InventoryBalance::where('warehouse_id', $warehouse->id)->where('inventory_item_id', $item->inventory_item_id);
        if ($warehouse->legacy_atk && ! (clone $balances)->exists()) {
            return (float) $item->current_stock - InventoryBalance::where('inventory_item_id', $item->inventory_item_id)->sum('quantity_milli') / 1000;
        }

        return $balances->sum('quantity_milli') / 1000;
    }

    public function atkWarehouse(AtkRequest $request): InventoryWarehouse
    {
        if ($request->inventoryWarehouse && ! $request->inventoryWarehouse->legacy_atk) {
            $warehouse = $request->inventoryWarehouse;
            if (! InventoryAccess::warehouseMatches($warehouse, $request->company?->code ?? '', $request->need_type ?? 'HO', $request->project_numbering_id)) {
                throw ValidationException::withMessages(['warehouse' => 'Gudang tidak sesuai entitas dan kebutuhan permintaan ATK.']);
            }

            return $warehouse;
        }
        if (in_array($request->company?->code, ['KPMOG', 'APCA'], true)) {
            return app(Warehouses::class)->forContext($request->company->code, $request->need_type ?? 'HO', $request->project_numbering_id);
        }

        throw ValidationException::withMessages(['warehouse' => 'Tetapkan entitas permintaan ATK sebelum menyerahkan barang.']);
    }

    public function allocateLegacy(User $actor, AtkItem $atk, array $quantities, string $token): void
    {
        abort_unless(InventoryAccess::administer($actor) || ($actor->isActiveForAccess() && $actor->hasPermission('atk.manage')), 403);
        Validator::make(['token' => $token], ['token' => 'required|uuid'])->validate();
        DB::transaction(function () use ($actor, $atk, $quantities, $token): void {
            $atk = AtkItem::lockForUpdate()->findOrFail($atk->id);
            $item = $atk->inventoryItem()->lockForUpdate()->firstOrFail();
            $legacy = app(Warehouses::class)->legacyAtk();
            if (InventoryMovement::where('transaction_key', $token.'-KPMOG-out')->exists() || InventoryMovement::where('transaction_key', $token.'-APCA-out')->exists()) {
                return;
            }
            $this->syncLegacyAtk($atk, (float) $atk->current_stock, (float) $atk->current_stock, $actor, $token.'-sync');
            $source = $this->balance($legacy, $item, null);
            $total = 0;
            foreach (['KPMOG', 'APCA'] as $company) {
                $quantity = Decimal::parse($quantities[$company] ?? '0', 2) * 10;
                $total += $quantity;
                if ($quantity === 0) {
                    continue;
                }
                $warehouse = app(Warehouses::class)->forContext($company, 'HO', null);
                $this->move($source, -$quantity, 'allocation_out', $token.'-'.$company.'-out', $actor, 'Pemetaan saldo ATK lama ke '.$company.'.');
                $target = $this->balance($warehouse, $item, null);
                $this->move($target, $quantity, 'allocation_in', $token.'-'.$company.'-in', $actor, 'Pemetaan saldo ATK lama dari Gudang Utama.');
            }
            if ($total === 0) {
                throw ValidationException::withMessages(['stock' => 'Isi jumlah yang dialokasikan ke KPMOG atau APCA.']);
            }
        }, 3);
    }

    public function issueAtk(AtkItem $atk, InventoryWarehouse $warehouse, float $quantity, User $actor, int $requestItemId): void
    {
        $remaining = Decimal::parse(number_format($quantity, 3, '.', ''), 3);
        $balances = InventoryBalance::where('warehouse_id', $warehouse->id)->where('inventory_item_id', $atk->inventory_item_id)->orderBy('id')->lockForUpdate()->get();
        if ($balances->sum('quantity_milli') < $remaining) {
            throw ValidationException::withMessages(['qty' => 'Stok gudang permintaan ATK tidak mencukupi.']);
        }
        foreach ($balances as $balance) {
            $take = min($remaining, (int) $balance->quantity_milli);
            if ($take > 0) {
                $this->move($balance, -$take, 'atk_issue', (string) Str::uuid(), $actor, 'Penyerahan ATK dari gudang permintaan.', ['reference_type' => 'atk_request_item', 'reference_id' => $requestItemId]);
                $remaining -= $take;
            }
        }
    }

    public function transact(User $actor, string $operation, array $data): void
    {
        $data = Validator::make($data, ['balance_id' => 'required|integer|exists:inventory_balances,id',
            'quantity' => 'required', 'destination_id' => 'nullable|integer|exists:inventory_warehouses,id',
            'token' => 'required|uuid', 'notes' => 'required|string|max:2000'])->validate();
        abort_unless(in_array($operation, ['incoming', 'outgoing', 'transfer', 'department_transfer'], true), 422);
        $quantity = Decimal::parse($data['quantity'], 3);
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Jumlah harus lebih dari nol.']);
        }
        DB::transaction(function () use ($actor, $data, $quantity, $operation): void {
            $source = InventoryBalance::findOrFail($data['balance_id']);
            abort_unless(InventoryAccess::administer($actor) || ($operation === 'outgoing' && InventoryAccess::issueDepartment($actor, $source)), 403);
            if ($atk = AtkItem::where('inventory_item_id', $source->inventory_item_id)->first()) {
                AtkItem::whereKey($atk->id)->lockForUpdate()->firstOrFail();
            }
            $item = InventoryItem::lockForUpdate()->findOrFail($source->inventory_item_id);
            if (($item->kind === 'atk' && $quantity % 10 !== 0) || ($item->kind === 'device' && $quantity % 1000 !== 0)) {
                throw ValidationException::withMessages(['quantity' => 'Jumlah ATK maksimal dua desimal dan perangkat harus utuh.']);
            }
            $source = InventoryBalance::lockForUpdate()->findOrFail($source->id);
            if (InventoryMovement::where('transaction_key', $data['token'].'-source')->exists()) {
                return;
            }
            $source->load('warehouse');
            if (! $source->warehouse->is_active || ! $item->is_active || $source->warehouse->legacy_atk) {
                throw ValidationException::withMessages(['stock' => 'Gunakan transaksi ATK untuk saldo Gudang Utama lama. Barang dan gudang harus aktif.']);
            }
            if ($operation === 'incoming' && $source->warehouse->kind === 'PROJECT' && ! $source->warehouse->project?->is_active) {
                throw ValidationException::withMessages(['stock' => 'Project sudah nonaktif.']);
            }
            $sign = $operation === 'incoming' ? 1 : -1;
            $this->move($source, $quantity * $sign, in_array($operation, ['transfer', 'department_transfer'], true) ? 'transfer_out' : $operation, $data['token'].'-source', $actor, $data['notes']);
            if (in_array($operation, ['transfer', 'department_transfer'], true)) {
                if ($operation === 'department_transfer') {
                    if (! $source->department_id) {
                        throw ValidationException::withMessages(['destination_id' => 'Saldo belum memiliki departemen asal PR.']);
                    }
                    $warehouse = app(Warehouses::class)->forDepartment($source->warehouse, $source->department()->firstOrFail());
                } else {
                    $warehouse = InventoryWarehouse::findOrFail($data['destination_id'] ?? null);
                }
                if (! $warehouse->is_active || ($warehouse->kind === 'PROJECT' && ! $warehouse->project?->is_active) || $warehouse->legacy_atk || $warehouse->company !== $source->warehouse->company || $warehouse->id === $source->warehouse_id) {
                    throw ValidationException::withMessages(['destination_id' => 'Pilih gudang tujuan aktif lain dalam entitas yang sama.']);
                }
                $target = $this->balance($warehouse, $item, $source->department_id);
                $this->move($target, $quantity, 'transfer_in', $data['token'].'-destination', $actor, $data['notes']);
            }
        }, 3);
    }
}
