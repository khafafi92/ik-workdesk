<?php

use App\Services\Scm\VendorForm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['FOREIGN_DEED', 'FOREIGN_LICENSE', 'FOREIGN_REGISTRY', 'FOREIGN_DOMICILE', 'HSE_POLICY', 'LIFTING_HOISTING', 'DAILY_INSPECTION', 'EQUIPMENT_INSPECTION', 'INTERNAL_BLASTING', 'VESSEL_LOADING'] as $code) {
            [$name, $step, , $expiry] = VendorForm::documents()[$code];
            if (! DB::table('scm_document_types')->where('code', $code)->exists()) {
                DB::table('scm_document_types')->insert(['code' => $code, 'name' => $name, 'description' => 'Form 005 perusahaan luar Indonesia, bagian '.$step,
                    'has_expiry' => $expiry, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Jenis dokumen vendor dipertahankan untuk melindungi kiriman dan riwayat pemeriksaan.');
    }
};
