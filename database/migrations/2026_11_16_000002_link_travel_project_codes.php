<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_documents', function (Blueprint $table): void {
            $table->foreignId('project_numbering_id')->nullable()->constrained('scm_project_numbering')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('travel_documents')->whereNotNull('project_numbering_id')->exists()) {
            throw new RuntimeException('Relasi kode pekerjaan SPD/LPJ sudah dipakai dan harus dipertahankan.');
        }
        Schema::table('travel_documents', fn (Blueprint $table) => $table->dropConstrainedForeignId('project_numbering_id'));
    }
};
