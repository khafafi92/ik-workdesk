<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_po_payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('purchase_order_id')->constrained('scm_purchase_orders')->restrictOnDelete();
            $table->string('kind', 30);
            $table->string('description', 255);
            $table->string('timing', 30);
            $table->date('due_on')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 10);
            $table->string('status', 20)->default('planned');
            $table->string('invoice_number', 255)->nullable();
            $table->date('paid_on')->nullable();
            $table->string('payment_reference', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['purchase_order_id', 'status']);
        });
        Schema::create('finance_po_payment_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('finance_po_payments')->restrictOnDelete();
            $table->string('type', 20);
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['payment_id', 'type']);
        });
        Schema::create('finance_po_payment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('finance_po_payments')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 50);
            $table->json('details');
            $table->text('reason')->nullable();
            $table->timestamp('created_at');
        });
        DB::table('permissions')->insertOrIgnore(['code' => 'finance.payments', 'name' => 'Kelola pembayaran PO',
            'module' => 'Finance', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (DB::table('finance_po_payments')->exists()) {
            throw new RuntimeException('Pertahankan riwayat pembayaran PO.');
        }
        Schema::dropIfExists('finance_po_payment_events');
        Schema::dropIfExists('finance_po_payment_files');
        Schema::dropIfExists('finance_po_payments');
    }
};
