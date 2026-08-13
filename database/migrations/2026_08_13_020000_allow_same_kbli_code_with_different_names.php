<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permit_kblis', function (Blueprint $table): void {
            $table->dropUnique('permit_kblis_permit_company_id_code_unique');
            $table->unique(
                ['permit_company_id', 'code', 'name'],
                'permit_kblis_company_code_name_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('permit_kblis', function (Blueprint $table): void {
            $table->dropUnique('permit_kblis_company_code_name_unique');
            $table->unique(['permit_company_id', 'code']);
        });
    }
};
