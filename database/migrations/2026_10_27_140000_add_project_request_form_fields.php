<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scm_requests', function (Blueprint $table): void {
            $table->foreignId('work_project_id')->nullable()->constrained('work_projects')->restrictOnDelete();
            $table->text('instruction_to_vendor')->nullable();
            $table->text('procedure_required')->nullable();
        });
        Schema::table('scm_request_items', function (Blueprint $table): void {
            $table->text('materials_needed')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('scm_requests')->whereNotNull('work_project_id')->orWhereNotNull('instruction_to_vendor')->orWhereNotNull('procedure_required')->exists()
            || DB::table('scm_request_items')->whereNotNull('materials_needed')->exists()) {
            throw new RuntimeException('Data formulir Project yang sudah tersimpan harus dipertahankan.');
        }
        Schema::table('scm_request_items', fn (Blueprint $table) => $table->dropColumn('materials_needed'));
        Schema::table('scm_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('work_project_id');
            $table->dropColumn(['instruction_to_vendor', 'procedure_required']);
        });
    }
};
