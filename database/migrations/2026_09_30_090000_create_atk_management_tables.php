<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atk_items', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category')->nullable()->index();
            $table->string('unit', 50);
            $table->decimal('current_stock', 15, 2)->default(0);
            $table->decimal('minimum_stock', 15, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('atk_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('request_number')->unique();
            $table->date('request_date')->index();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->text('purpose')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('atk_request_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('atk_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('atk_item_id')->constrained()->restrictOnDelete();
            $table->decimal('qty_requested', 15, 2);
            $table->decimal('qty_issued', 15, 2)->default(0);
            $table->decimal('qty_received', 15, 2)->default(0);
            $table->string('unit', 50);
            $table->string('status')->default('pending')->index();
            $table->text('requester_note')->nullable();
            $table->text('ga_note')->nullable();
            $table->timestamp('waiting_procurement_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['atk_request_id', 'atk_item_id']);
        });

        Schema::create('atk_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('atk_item_id')->constrained()->restrictOnDelete();
            $table->string('movement_type')->index();
            $table->decimal('qty', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('atk_department_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('atk_item_id')->constrained()->restrictOnDelete();
            $table->decimal('qty_available', 15, 2)->default(0);
            $table->timestamp('last_received_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->unique(['department_id', 'atk_item_id']);
        });

        Schema::create('atk_department_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('atk_item_id')->constrained()->restrictOnDelete();
            $table->string('movement_type')->index();
            $table->decimal('qty', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->foreignId('atk_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('atk_request_item_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['department_id', 'atk_item_id']);
        });

        Schema::create('atk_usage_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('atk_item_id')->constrained()->restrictOnDelete();
            $table->decimal('qty_used', 15, 2);
            $table->date('usage_date')->index();
            $table->text('purpose')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('used_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['department_id', 'atk_item_id']);
        });

        Schema::create('atk_request_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('atk_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('atk_request_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['atk_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atk_request_histories');
        Schema::dropIfExists('atk_usage_transactions');
        Schema::dropIfExists('atk_department_stock_movements');
        Schema::dropIfExists('atk_department_balances');
        Schema::dropIfExists('atk_stock_movements');
        Schema::dropIfExists('atk_request_items');
        Schema::dropIfExists('atk_requests');
        Schema::dropIfExists('atk_items');
    }
};
