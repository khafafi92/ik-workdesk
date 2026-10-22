<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scm_categories', function (Blueprint $t): void {
            $t->id();
            $t->string('name')->unique();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('scm_document_types', function (Blueprint $t): void {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->text('description')->nullable();
            $t->boolean('has_expiry')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('scm_requirements', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('category_id')->constrained('scm_categories')->restrictOnDelete();
            $t->foreignId('document_type_id')->constrained('scm_document_types')->restrictOnDelete();
            $t->boolean('is_required')->default(true);
            $t->unique(['category_id', 'document_type_id']);
            $t->timestamps();
        });
        Schema::create('scm_vendors', function (Blueprint $t): void {
            $t->id();
            $t->string('code')->unique();
            $t->string('name')->index();
            $t->string('provider_type');
            $t->string('business_category')->nullable();
            $t->text('address')->nullable();
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->string('pic_name')->nullable();
            $t->string('pic_position')->nullable();
            $t->string('pic_phone')->nullable();
            $t->string('pic_email')->nullable();
            $t->boolean('is_active')->default(true);
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        Schema::create('scm_requests', function (Blueprint $t): void {
            $t->id();
            $t->string('number')->unique();
            $t->string('kind', 2);
            $t->date('date');
            $t->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $t->foreignId('category_id')->constrained('scm_categories')->restrictOnDelete();
            $t->string('title');
            $t->text('reason');
            $t->date('needed_at')->nullable();
            $t->string('location')->nullable();
            $t->foreignId('scm_pic_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('status')->default('draft')->index();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->text('notes')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->index(['requester_id', 'status']);
            $t->index(['department_id', 'status']);
        });
        Schema::create('scm_request_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('request_id')->constrained('scm_requests')->restrictOnDelete();
            $t->unsignedInteger('position');
            $t->string('name');
            $t->text('specification');
            $t->unsignedBigInteger('quantity_milli');
            $t->string('unit');
            $t->text('notes')->nullable();
            $t->unique(['request_id', 'position']);
            $t->timestamps();
        });
        Schema::create('scm_candidates', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('request_id')->constrained('scm_requests')->restrictOnDelete();
            $t->foreignId('vendor_id')->constrained('scm_vendors')->restrictOnDelete();
            $t->date('requested_at')->nullable();
            $t->string('followup_status')->default('belum_diminta');
            $t->unique(['request_id', 'vendor_id']);
            $t->timestamps();
        });
        Schema::create('scm_quotations', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('candidate_id')->constrained('scm_candidates')->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->string('number');
            $t->date('date');
            $t->date('valid_until')->nullable();
            $t->string('currency', 3);
            $t->string('lead_time');
            $t->text('payment_terms');
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('extra_minor')->default(0);
            $t->unsignedBigInteger('total_minor');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->unique(['candidate_id', 'version']);
            $t->timestamps();
        });
        Schema::create('scm_quotation_lines', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('quotation_id')->constrained('scm_quotations')->restrictOnDelete();
            $t->foreignId('request_item_id')->constrained('scm_request_items')->restrictOnDelete();
            $t->text('description');
            $t->unsignedBigInteger('quantity_milli');
            $t->string('unit');
            $t->unsignedBigInteger('price_minor');
            $t->unsignedBigInteger('discount_minor')->default(0);
            $t->unsignedBigInteger('tax_minor')->default(0);
            $t->unsignedBigInteger('total_minor');
            $t->unique(['quotation_id', 'request_item_id']);
            $t->timestamps();
        });
        Schema::create('scm_cbes', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('request_id')->unique()->constrained('scm_requests')->restrictOnDelete();
            $t->string('number')->unique();
            $t->date('date');
            $t->string('status')->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->json('quotation_ids')->nullable();
            $t->foreignId('selected_vendor_id')->nullable()->constrained('scm_vendors')->restrictOnDelete();
            $t->unsignedBigInteger('final_minor')->nullable();
            $t->text('selection_reason')->nullable();
            $t->json('snapshot')->nullable();
            $t->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('finalized_at')->nullable();
            $t->timestamps();
        });
        Schema::create('scm_vendor_documents', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('vendor_id')->constrained('scm_vendors')->restrictOnDelete();
            $t->foreignId('document_type_id')->constrained('scm_document_types')->restrictOnDelete();
            $t->string('number');
            $t->string('title');
            $t->date('issued_at')->nullable();
            $t->date('expires_at')->nullable();
            $t->unsignedInteger('version');
            $t->timestamp('received_at');
            $t->text('notes')->nullable();
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->unique(['vendor_id', 'document_type_id', 'version'], 'scm_document_version_unique');
            $t->timestamps();
        });
        Schema::create('scm_legal_cases', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('request_id')->unique()->constrained('scm_requests')->restrictOnDelete();
            $t->foreignId('cbe_id')->unique()->constrained('scm_cbes')->restrictOnDelete();
            $t->foreignId('vendor_id')->constrained('scm_vendors')->restrictOnDelete();
            $t->foreignId('scm_pic_id')->constrained('users')->restrictOnDelete();
            $t->timestamp('requested_at');
            $t->timestamp('completed_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('scm_legal_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('legal_case_id')->constrained('scm_legal_cases')->restrictOnDelete();
            $t->foreignId('document_type_id')->constrained('scm_document_types')->restrictOnDelete();
            $t->string('document_name');
            $t->boolean('has_expiry')->default(false);
            $t->boolean('is_required');
            $t->string('status')->default('belum_diminta');
            $t->timestamp('requested_at')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->foreignId('vendor_document_id')->nullable()->constrained('scm_vendor_documents')->restrictOnDelete();
            $t->text('correction_notes')->nullable();
            $t->unique(['legal_case_id', 'document_type_id']);
            $t->timestamps();
        });
        Schema::create('scm_attachments', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('request_id')->nullable()->constrained('scm_requests')->restrictOnDelete();
            $t->foreignId('quotation_id')->nullable()->constrained('scm_quotations')->restrictOnDelete();
            $t->foreignId('cbe_id')->nullable()->constrained('scm_cbes')->restrictOnDelete();
            $t->foreignId('vendor_document_id')->nullable()->constrained('scm_vendor_documents')->restrictOnDelete();
            $t->string('path')->unique();
            $t->string('original_name');
            $t->string('mime');
            $t->unsignedBigInteger('size');
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('scm_audits', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('request_id')->nullable()->constrained('scm_requests')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('entity_type');
            $t->unsignedBigInteger('entity_id');
            $t->string('action');
            $t->json('changes');
            $t->text('reason')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['scm_audits', 'scm_attachments', 'scm_legal_items', 'scm_legal_cases', 'scm_vendor_documents', 'scm_cbes', 'scm_quotation_lines', 'scm_quotations', 'scm_candidates', 'scm_request_items', 'scm_requests', 'scm_vendors', 'scm_requirements', 'scm_document_types', 'scm_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
