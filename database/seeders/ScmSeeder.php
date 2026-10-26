<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Scm\DocumentType;
use App\Services\Scm\VendorForm;
use Illuminate\Database\Seeder;

class ScmSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['scm.request' => 'Buat PR/SR sendiri', 'scm.process' => 'Proses pengadaan SCM', 'scm.configure' => 'Kelola konfigurasi SCM'] as $code => $name) {
            Permission::firstOrCreate(['code' => $code], ['name' => $name, 'module' => 'SCM', 'is_active' => true]);
        }
        foreach (['NPWP' => false, 'NIB' => false, 'SBU' => true, 'KTP' => false] as $code => $expiry) {
            DocumentType::firstOrCreate(['code' => $code], ['name' => $code, 'has_expiry' => $expiry, 'is_active' => true]);
        }
        foreach (VendorForm::documents() as $code => [$name, $step, $rule, $expiry]) {
            DocumentType::firstOrCreate(['code' => $code], ['name' => $name, 'description' => 'Form 005, bagian '.$step, 'has_expiry' => $expiry, 'is_active' => true]);
        }
    }
}
