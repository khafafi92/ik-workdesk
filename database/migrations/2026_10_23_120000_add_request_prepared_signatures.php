<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scm_user_signatures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('path')->unique();
            $table->string('mime', 50);
            $table->timestamps();
        });
        Schema::table('scm_requests', function (Blueprint $table): void {
            $table->foreignId('prepared_signature_id')->nullable()->constrained('scm_user_signatures')->restrictOnDelete();
            $table->timestamp('prepared_signed_at')->nullable();
            $table->string('prepared_signed_name')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('scm_user_signatures')->exists()) {
            throw new RuntimeException('Migrasi TTD tidak dapat dibatalkan setelah gambar tanda tangan disimpan.');
        }
        Schema::table('scm_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('prepared_signature_id');
            $table->dropColumn(['prepared_signed_at', 'prepared_signed_name']);
        });
        Schema::dropIfExists('scm_user_signatures');
    }
};
