<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scm_vendor_portal_accesses', function (Blueprint $table): void {
            $table->id();
            $table->string('code_hash', 64)->unique();
            $table->foreignId('vendor_id')->nullable()->constrained('scm_vendors')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::table('scm_vendor_registrations', function (Blueprint $table): void {
            $table->foreignId('vendor_id')->nullable()->change();
            $table->foreignId('invited_by')->nullable()->change();
            $table->unsignedInteger('vendor_version')->nullable()->change();
            $table->foreignId('portal_access_id')->nullable()->constrained('scm_vendor_portal_accesses')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('scm_vendor_portal_accesses')->exists()) {
            throw new RuntimeException('Portal vendor sudah digunakan. Pertahankan nomor akses dan riwayat pendaftaran.');
        }
        Schema::table('scm_vendor_registrations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('portal_access_id');
            $table->foreignId('vendor_id')->nullable(false)->change();
            $table->foreignId('invited_by')->nullable(false)->change();
            $table->unsignedInteger('vendor_version')->nullable(false)->change();
        });
        Schema::dropIfExists('scm_vendor_portal_accesses');
    }
};
