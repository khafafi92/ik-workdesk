<?php

use App\Services\Scm\ApcaVendorChecklist;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scm_vendor_registration_files', function (Blueprint $table): void {
            $table->text('notes')->nullable();
        });

        foreach (ApcaVendorChecklist::documents() as $code => [$name]) {
            DB::table('scm_document_types')->insertOrIgnore([
                'code' => $code, 'name' => $name, 'description' => 'Checklist Registrasi Vendor APCA v3',
                'has_expiry' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (DB::table('scm_vendor_registrations')->where('context->document_profile', ApcaVendorChecklist::PROFILE)->exists()) {
            throw new RuntimeException('Checklist APCA sudah digunakan. Riwayat registrasi harus dipertahankan.');
        }

        DB::table('scm_document_types')->whereIn('code', array_keys(ApcaVendorChecklist::documents()))->delete();
        Schema::table('scm_vendor_registration_files', function (Blueprint $table): void {
            $table->dropColumn('notes');
        });
    }
};
