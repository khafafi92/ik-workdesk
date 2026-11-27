<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_request_policies', function (Blueprint $table): void {
            $table->unsignedBigInteger('approval_department_id')->default(0);
            $table->unique(['company', 'kind', 'approval_department_id'], 'finance_request_policies_scope_unique');
        });
        Schema::table('finance_request_policies', function (Blueprint $table): void {
            $table->dropUnique(['company', 'kind']);
        });
    }

    public function down(): void
    {
        if (DB::table('finance_request_policies')->where('approval_department_id', '!=', 0)->exists()) {
            throw new RuntimeException('Department-specific approval policies must be retained.');
        }

        Schema::table('finance_request_policies', function (Blueprint $table): void {
            $table->unique(['company', 'kind']);
            $table->dropUnique('finance_request_policies_scope_unique');
            $table->dropColumn('approval_department_id');
        });
    }
};
