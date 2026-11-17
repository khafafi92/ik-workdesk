<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_warehouses', fn (Blueprint $table) => $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete());
        Schema::table('inventory_receipts', fn (Blueprint $table) => $table->foreignId('purchase_order_id')->nullable()->constrained('scm_purchase_orders')->restrictOnDelete());
        DB::table('permissions')->insertOrIgnore(['code' => 'inventory.department-issue', 'name' => 'Distribusi stok gudang departemen',
            'module' => 'Inventori', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (DB::table('inventory_warehouses')->whereNotNull('department_id')->exists()
            || DB::table('inventory_receipts')->whereNotNull('purchase_order_id')->exists()) {
            throw new RuntimeException('Gudang departemen atau penerimaan PO sudah digunakan.');
        }
        Schema::table('inventory_receipts', fn (Blueprint $table) => $table->dropConstrainedForeignId('purchase_order_id'));
        Schema::table('inventory_warehouses', fn (Blueprint $table) => $table->dropConstrainedForeignId('department_id'));
        $ids = DB::table('permissions')->where('code', 'inventory.department-issue')->pluck('id');
        DB::table('permission_user')->whereIn('permission_id', $ids)->delete();
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
