<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Scm\DocumentType;
use Illuminate\Database\Seeder;

class ScmSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['scm.request' => 'Buat PR/SR sendiri', 'scm.process' => 'Proses pengadaan SCM', 'scm.configure' => 'Kelola konfigurasi SCM'] as $code => $name) {
            Permission::firstOrCreate(['code' => $code], ['name' => $name, 'module' => 'SCM', 'is_active' => true]);
        }
        foreach (['NPWP' => false, 'NIB' => false, 'SBU' => true] as $code => $expiry) {
            DocumentType::firstOrCreate(['code' => $code], ['name' => $code, 'has_expiry' => $expiry, 'is_active' => true]);
        }
    }
}
