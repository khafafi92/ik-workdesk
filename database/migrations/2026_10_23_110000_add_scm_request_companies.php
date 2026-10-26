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
            $table->string('company', 10)->default('APCA');
            $table->string('delivery_point')->nullable();
        });
        Schema::table('scm_request_items', function (Blueprint $table): void {
            $table->string('size')->nullable();
            $table->string('model_part_number')->nullable();
        });
        Schema::table('scm_request_numbers', function (Blueprint $table): void {
            $table->string('company', 10)->default('APCA');
        });
        Schema::table('scm_number_sequences', function (Blueprint $table): void {
            $table->dropUnique(['kind', 'year']);
            $table->string('company', 10)->default('APCA');
            $table->unique(['company', 'kind', 'year']);
        });
    }

    public function down(): void
    {
        if (DB::table('scm_request_numbers')->where('company', '!=', 'APCA')->exists()
            || DB::table('scm_requests')->where('company', '!=', 'APCA')->exists()
            || DB::table('scm_number_sequences')->where('company', '!=', 'APCA')->exists()) {
            throw new RuntimeException('Migrasi perusahaan tidak dapat dibatalkan setelah nomor atau permintaan KPMOG dibuat.');
        }
        Schema::table('scm_number_sequences', function (Blueprint $table): void {
            $table->dropUnique(['company', 'kind', 'year']);
            $table->dropColumn('company');
            $table->unique(['kind', 'year']);
        });
        Schema::table('scm_request_numbers', fn (Blueprint $table) => $table->dropColumn('company'));
        Schema::table('scm_request_items', fn (Blueprint $table) => $table->dropColumn(['size', 'model_part_number']));
        Schema::table('scm_requests', fn (Blueprint $table) => $table->dropColumn(['company', 'delivery_point']));
    }
};
