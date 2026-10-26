<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scm_legal_cases', function (Blueprint $table): void {
            $table->string('document_basis')->default('requirements');
            $table->json('document_basis_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('scm_legal_cases', function (Blueprint $table): void {
            $table->dropColumn(['document_basis', 'document_basis_snapshot']);
        });
    }
};
