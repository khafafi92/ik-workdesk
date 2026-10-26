<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scm_cbes', function (Blueprint $table): void {
            $table->unsignedTinyInteger('vat_percent')->nullable();
            $table->json('commercial_terms')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('scm_cbes', function (Blueprint $table): void {
            $table->dropColumn(['vat_percent', 'commercial_terms']);
        });
    }
};
