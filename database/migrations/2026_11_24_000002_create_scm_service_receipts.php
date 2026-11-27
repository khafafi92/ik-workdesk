<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scm_service_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('scm_purchase_orders')->restrictOnDelete();
            $table->uuid('token');
            $table->string('payload_hash', 64);
            $table->date('received_on');
            $table->string('reference');
            $table->text('notes')->nullable();
            $table->foreignId('accepted_by')->constrained('users')->restrictOnDelete();
            $table->string('accepted_by_name');
            $table->string('evidence_path')->nullable();
            $table->string('evidence_name')->nullable();
            $table->string('evidence_mime')->nullable();
            $table->timestamps();
            $table->unique(['purchase_order_id', 'token']);
        });

        Schema::create('scm_service_receipt_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_receipt_id')->constrained('scm_service_receipts')->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->unsignedBigInteger('quantity_milli');
            $table->timestamps();
            $table->unique(['service_receipt_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scm_service_receipt_lines');
        Schema::dropIfExists('scm_service_receipts');
    }
};
