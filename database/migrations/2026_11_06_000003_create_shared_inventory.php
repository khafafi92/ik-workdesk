<?php

use App\Services\Inventory\InventoryBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('kind', 20);
            $table->string('category')->nullable();
            $table->string('unit', 50);
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('size', 100)->nullable();
            $table->text('specification')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        Schema::create('inventory_warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->string('kind', 10);
            $table->string('company', 10)->nullable();
            $table->foreignId('project_numbering_id')->nullable()->constrained('scm_project_numbering')->restrictOnDelete();
            $table->boolean('legacy_atk')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('inventory_balances', function (Blueprint $table): void {
            $table->id();
            $table->string('balance_key', 120)->unique();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->bigInteger('quantity_milli')->default(0);
            $table->timestamps();
        });
        Schema::create('inventory_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('request_id')->constrained('scm_requests')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->restrictOnDelete();
            $table->date('received_on');
            $table->string('reference', 255);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->string('transaction_key', 150)->unique();
            $table->foreignId('balance_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->string('type', 30);
            $table->bigInteger('quantity_milli');
            $table->bigInteger('balance_before_milli');
            $table->bigInteger('balance_after_milli');
            $table->foreignId('receipt_id')->nullable()->constrained('inventory_receipts')->restrictOnDelete();
            $table->foreignId('request_item_id')->nullable()->constrained('scm_request_items')->restrictOnDelete();
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['receipt_id', 'request_item_id']);
        });
        Schema::table('atk_items', fn (Blueprint $table) => $table->foreignId('inventory_item_id')->nullable()->unique()->constrained('inventory_items')->restrictOnDelete());
        Schema::table('atk_requests', fn (Blueprint $table) => $table->foreignId('inventory_warehouse_id')->nullable()->constrained('inventory_warehouses')->restrictOnDelete());
        Schema::table('scm_request_items', fn (Blueprint $table) => $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->restrictOnDelete());
        Schema::table('it_devices', function (Blueprint $table): void {
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->restrictOnDelete();
            $table->foreignId('inventory_warehouse_id')->nullable()->constrained('inventory_warehouses')->restrictOnDelete();
            $table->foreignId('inventory_movement_id')->nullable()->unique()->constrained('inventory_movements')->restrictOnDelete();
        });
        DB::table('permissions')->insertOrIgnore(['code' => 'inventory.manage', 'name' => 'Kelola master dan mutasi inventori',
            'module' => 'Inventori', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('permissions')->insertOrIgnore(['code' => 'inventory.view-all', 'name' => 'Lihat inventori seluruh entitas',
            'module' => 'Inventori', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        app(InventoryBackfill::class)->run();
    }

    public function down(): void
    {
        if (DB::table('inventory_receipts')->exists() || DB::table('inventory_movements')->whereNotIn('type', ['opening', 'atk_sync'])->exists()) {
            throw new RuntimeException('Inventori sudah memiliki transaksi. Pulihkan dari backup untuk membatalkan migrasi.');
        }
        Schema::table('it_devices', function (Blueprint $table): void {
            $table->dropUnique(['inventory_movement_id']);
            $table->dropConstrainedForeignId('inventory_movement_id');
            $table->dropConstrainedForeignId('inventory_warehouse_id');
            $table->dropConstrainedForeignId('inventory_item_id');
        });
        Schema::table('scm_request_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('inventory_item_id'));
        Schema::table('atk_requests', fn (Blueprint $table) => $table->dropConstrainedForeignId('inventory_warehouse_id'));
        Schema::table('atk_items', function (Blueprint $table): void {
            $table->dropUnique(['inventory_item_id']);
            $table->dropConstrainedForeignId('inventory_item_id');
        });
        foreach (['inventory_movements', 'inventory_receipts', 'inventory_balances', 'inventory_warehouses', 'inventory_items'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
