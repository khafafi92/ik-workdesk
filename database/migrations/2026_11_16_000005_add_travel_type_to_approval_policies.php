<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_approval_policies', function (Blueprint $table): void {
            $table->string('travel_type', 10)->default('HO')->after('document_kind');
            $table->dropUnique('travel_approval_scope_unique');
            $table->unique(['company', 'department_id', 'document_kind', 'travel_type'], 'travel_approval_scope_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('travel_approval_policies', function (Blueprint $table): void {
            $table->dropUnique('travel_approval_scope_type_unique');
            $table->dropColumn('travel_type');
            $table->unique(['company', 'department_id', 'document_kind'], 'travel_approval_scope_unique');
        });
    }
};
