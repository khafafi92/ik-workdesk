<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_devices', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('category', 40)->index();
            $table->string('device_type', 40)->index();
            $table->string('object_kind', 20)->index();
            $table->string('name');
            $table->string('asset_number')->nullable()->unique();
            $table->string('serial_number')->nullable()->index();
            $table->string('manufacturer')->nullable();
            $table->string('model_name')->nullable();
            $table->text('specifications')->nullable();
            $table->foreignId('work_location_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('location_detail')->nullable();
            $table->foreignId('managing_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('assigned_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->string('assigned_user_name')->nullable();
            $table->foreignId('parent_device_id')->nullable()->constrained('it_devices')->restrictOnDelete();
            $table->string('lifecycle_status', 30)->nullable()->index();
            $table->date('disposed_at')->nullable();
            $table->text('disposal_reason')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('it_device_computer_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('it_device_id')->unique()->constrained()->restrictOnDelete();
            $table->string('operating_system')->nullable();
            $table->string('windows_version')->nullable();
            $table->string('office_version')->nullable();
            foreach (['windows', 'office'] as $product) {
                $table->string($product.'_license_status', 30)->default('unverified');
                $table->foreignId($product.'_verified_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp($product.'_verified_at')->nullable();
                $table->text($product.'_verification_notes')->nullable();
            }
            $table->decimal('ram_gb', 10, 2)->nullable();
            $table->json('storage_devices')->nullable();
            $table->timestamps();
        });

        Schema::create('it_checklist_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80);
            $table->unsignedInteger('version');
            $table->string('name');
            $table->string('category', 40);
            $table->string('device_type', 40);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['code', 'version']);
            $table->unique(['device_type', 'version']);
        });

        Schema::create('it_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('it_checklist_template_id')->constrained()->restrictOnDelete();
            $table->string('code', 80);
            $table->string('section', 100);
            $table->string('label');
            $table->text('instructions');
            $table->unsignedInteger('sort_order');
            $table->string('value_type', 20)->default('text');
            $table->string('unit', 30)->nullable();
            $table->timestamps();
            $table->unique(['it_checklist_template_id', 'code'], 'it_items_template_code_unique');
            $table->unique(['id', 'it_checklist_template_id'], 'it_items_id_template_unique');
        });

        Schema::create('it_inspections', function (Blueprint $table): void {
            $table->id();
            $table->string('inspection_number', 100)->unique();
            $table->foreignId('it_device_id')->constrained()->restrictOnDelete();
            $table->foreignId('it_checklist_template_id')->constrained()->restrictOnDelete();
            $table->timestamp('checked_at')->index();
            $table->foreignId('inspector_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('managing_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('work_location_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('location_detail')->nullable();
            $table->json('device_snapshot');
            $table->json('inspector_snapshot');
            $table->json('location_snapshot');
            $table->string('status', 20)->default('draft')->index();
            $table->string('overall_condition', 30)->default('unchecked')->index();
            $table->json('condition_counts')->nullable();
            $table->text('notes')->nullable();
            $table->text('finalization_notes')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['it_device_id', 'status', 'checked_at']);
            $table->index(['managing_department_id', 'checked_at'], 'it_inspections_department_date_index');
            $table->unique(['id', 'it_checklist_template_id'], 'it_inspections_id_template_unique');
        });

        Schema::create('it_inspection_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('it_inspection_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('it_checklist_item_id');
            $table->unsignedBigInteger('it_checklist_template_id');
            $table->json('item_snapshot');
            $table->string('condition', 30)->default('unchecked')->index();
            $table->json('measured_value')->nullable();
            $table->text('notes')->nullable();
            $table->text('finding')->nullable();
            $table->text('not_applicable_reason')->nullable();
            $table->timestamps();
            $table->unique(['it_inspection_id', 'it_checklist_item_id'], 'it_results_inspection_item_unique');
            $table->unique(['id', 'it_inspection_id'], 'it_results_id_inspection_unique');
            $table->foreign(['it_checklist_item_id', 'it_checklist_template_id'], 'it_results_item_template_fk')
                ->references(['id', 'it_checklist_template_id'])->on('it_checklist_items')->restrictOnDelete();
            $table->foreign(['it_inspection_id', 'it_checklist_template_id'], 'it_results_inspection_template_fk')
                ->references(['id', 'it_checklist_template_id'])->on('it_inspections')->restrictOnDelete();
        });

        Schema::create('it_inspection_follow_ups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('it_inspection_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('it_inspection_result_id')->unique();
            $table->text('action_plan');
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->timestamp('due_at')->nullable()->index();
            $table->string('status', 20)->default('open')->index();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->foreign(['it_inspection_result_id', 'it_inspection_id'], 'it_follow_ups_result_inspection_fk')
                ->references(['id', 'it_inspection_id'])->on('it_inspection_results')->restrictOnDelete();
        });

        Schema::create('it_inspection_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('it_inspection_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 80);
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['it_inspection_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_inspection_events');
        Schema::dropIfExists('it_inspection_follow_ups');
        Schema::dropIfExists('it_inspection_results');
        Schema::dropIfExists('it_inspections');
        Schema::dropIfExists('it_checklist_items');
        Schema::dropIfExists('it_checklist_templates');
        Schema::dropIfExists('it_device_computer_details');
        Schema::dropIfExists('it_devices');
    }
};
