<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scm_purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->unique()->constrained('scm_requests')->restrictOnDelete();
            $table->foreignId('cbe_id')->unique()->constrained('scm_cbes')->restrictOnDelete();
            $table->string('company');
            $table->string('number', 190);
            $table->string('number_key', 190);
            $table->date('issued_at');
            $table->text('supplier_address')->nullable();
            $table->string('order_contact')->nullable();
            $table->text('delivery_terms');
            $table->string('delivery_time');
            $table->text('payment_terms');
            $table->text('notes')->nullable();
            $table->json('snapshot');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company', 'number_key']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('scm_purchase_orders') && DB::table('scm_purchase_orders')->exists()) {
            throw new RuntimeException('Purchase Order sudah digunakan dan tidak dapat dihapus melalui rollback.');
        }
        Schema::dropIfExists('scm_purchase_orders');
    }
};
