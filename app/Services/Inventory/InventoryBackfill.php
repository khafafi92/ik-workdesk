<?php

namespace App\Services\Inventory;

use App\Services\Scm\Decimal;
use Illuminate\Support\Facades\DB;

class InventoryBackfill
{
    public function run(): void
    {
        $now = now();
        $warehouseId = DB::table('inventory_warehouses')->where('code', 'HO-ATK-LEGACY')->value('id')
            ?? DB::table('inventory_warehouses')->insertGetId(['code' => 'HO-ATK-LEGACY', 'name' => 'Office supplies\'s Main Storage',
                'kind' => 'HO', 'legacy_atk' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('atk_items')->whereNull('inventory_item_id')->orderBy('id')->chunkById(200, function ($items) use ($warehouseId, $now): void {
            foreach ($items as $item) {
                $catalogueId = DB::table('inventory_items')->insertGetId(['code' => 'ATK-'.$item->id, 'name' => $item->name, 'kind' => 'atk',
                    'category' => $item->category, 'unit' => $item->unit, 'size' => $item->size, 'is_active' => $item->is_active,
                    'created_at' => $now, 'updated_at' => $now]);
                DB::table('atk_items')->where('id', $item->id)->update(['inventory_item_id' => $catalogueId]);
                $quantity = Decimal::parse($item->current_stock, 3);
                $balanceId = DB::table('inventory_balances')->insertGetId(['balance_key' => $warehouseId.'-'.$catalogueId.'-0',
                    'warehouse_id' => $warehouseId, 'inventory_item_id' => $catalogueId, 'quantity_milli' => $quantity,
                    'created_at' => $now, 'updated_at' => $now]);
                DB::table('inventory_movements')->insert(['transaction_key' => 'opening-atk-'.$item->id, 'balance_id' => $balanceId,
                    'type' => 'opening', 'quantity_milli' => $quantity, 'balance_before_milli' => 0, 'balance_after_milli' => $quantity,
                    'reference_type' => 'atk_item', 'reference_id' => $item->id, 'notes' => 'Main Storage Saldo before inventory integration.',
                    'created_at' => $now, 'updated_at' => $now]);
            }
        });
        DB::table('atk_requests')->whereNull('inventory_warehouse_id')->update(['inventory_warehouse_id' => $warehouseId]);
        DB::table('it_devices')->where('object_kind', '!=', 'room')->whereNull('inventory_item_id')->orderBy('id')->chunkById(200, function ($devices) use ($now): void {
            foreach ($devices as $device) {
                $id = DB::table('inventory_items')->insertGetId(['code' => 'DEVICE-'.$device->id,
                    'name' => trim(($device->manufacturer ?? '').' '.($device->model_name ?? '')) ?: $device->device_type,
                    'kind' => 'device', 'unit' => 'PCS', 'brand' => $device->manufacturer, 'model' => $device->model_name,
                    'created_at' => $now, 'updated_at' => $now]);
                DB::table('it_devices')->where('id', $device->id)->update(['inventory_item_id' => $id]);
            }
        });
        foreach (['KPMOG', 'APCA'] as $company) {
            DB::table('inventory_warehouses')->insertOrIgnore(['code' => 'HO-'.$company, 'name' => 'HO warehouse', 'kind' => 'HO',
                'company' => $company, 'created_at' => $now, 'updated_at' => $now]);
            foreach (DB::table('scm_project_numbering')->get() as $project) {
                DB::table('inventory_warehouses')->insertOrIgnore(['code' => 'PROJECT-'.$company.'-'.$project->id, 'name' => $project->name,
                    'kind' => 'PROJECT', 'company' => $company, 'project_numbering_id' => $project->id,
                    'is_active' => $project->is_active, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }
}
