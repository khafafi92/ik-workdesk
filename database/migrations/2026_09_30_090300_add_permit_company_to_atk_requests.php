<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atk_requests', function (Blueprint $table): void {
            $table->foreignId('permit_company_id')
                ->nullable()
                ->after('department_id')
                ->constrained()
                ->nullOnDelete()
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('atk_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('permit_company_id');
        });
    }
};
