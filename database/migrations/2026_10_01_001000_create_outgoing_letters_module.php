<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('document_numbering_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('permit_company_id')->constrained('permit_companies')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('template', 500);
            $table->unsignedTinyInteger('running_digits')->default(3);
            $table->string('reset_period', 20)->default('yearly');
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['permit_company_id', 'department_id', 'document_type_id'], 'document_template_resolution_index');
        });

        Schema::create('document_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_numbering_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permit_company_id')->constrained('permit_companies')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_type_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedTinyInteger('month')->nullable();
            $table->string('scope_key')->unique();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('outgoing_letters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('permit_company_id')->constrained('permit_companies')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_project_id')->nullable()->constrained('work_projects')->nullOnDelete();
            $table->foreignId('document_numbering_template_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('running_number')->nullable();
            $table->string('document_number')->nullable()->unique();
            $table->date('document_date');
            $table->string('subject');
            $table->string('recipient')->nullable();
            $table->string('pin')->nullable();
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pic_name')->nullable();
            $table->string('location_code')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_legacy_number')->default(false);
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['permit_company_id', 'department_id', 'document_type_id'], 'outgoing_letter_context_index');
            $table->index(['status', 'document_date']);
        });

        $now = now();
        foreach ([
            ['letters.view', 'View Outgoing Letters', 'View outgoing-letter register.'],
            ['letters.create', 'Create Outgoing Letters', 'Create and edit own draft outgoing letters.'],
            ['letters.issue', 'Issue Outgoing Letters', 'Issue outgoing letters and reserve document numbers.'],
            ['letters.manage', 'Manage Letter Masters', 'Manage document types and numbering templates.'],
            ['letters.legacy-import', 'Input Legacy Letter Numbers', 'Record existing issued document numbers without regeneration.'],
            ['letters.outgoing', 'Access Outgoing Letters Menu', 'Open the outgoing-letter menu.'],
            ['letters.document-types', 'Access Document Types Menu', 'Open the document-types menu.'],
            ['letters.numbering-templates', 'Access Numbering Templates Menu', 'Open the numbering-templates menu.'],
        ] as [$code, $name, $description]) {
            DB::table('permissions')->updateOrInsert(['code' => $code], [
                'name' => $name,
                'module' => 'Surat',
                'description' => $description,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outgoing_letters');
        Schema::dropIfExists('document_number_sequences');
        Schema::dropIfExists('document_numbering_templates');
        Schema::dropIfExists('document_types');

        DB::table('permissions')->whereIn('code', [
            'letters.view', 'letters.create', 'letters.issue', 'letters.manage', 'letters.legacy-import',
            'letters.outgoing', 'letters.document-types', 'letters.numbering-templates',
        ])->delete();
    }
};
