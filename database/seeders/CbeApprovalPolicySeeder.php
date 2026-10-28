<?php

namespace Database\Seeders;

use App\Models\Scm\CbeApprovalPolicy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CbeApprovalPolicySeeder extends Seeder
{
    public function run(): void
    {
        $ids = [];
        foreach (['acknowledger_id' => 'meyfi', 'crosschecker_id' => 'dipta', 'approver_id' => 'lenaldy'] as $role => $name) {
            $matches = User::with(['employee', 'roles'])->get()->filter(fn ($user) => $user->isActiveForAccess() && preg_match('/\b'.preg_quote($name, '/').'\b/i', $user->name));
            if ($matches->count() !== 1) {
                throw new RuntimeException('Akun aktif '.$name.' belum ditemukan secara unik. Tetapkan akun melalui Master Approval CBE; seeder tidak mengubah data.');
            }
            $ids[$role] = $matches->first()->id;
        }
        if (count(array_unique($ids)) !== 3) {
            throw new RuntimeException('Penandatangan CBE harus tiga akun berbeda.');
        }
        DB::transaction(function () use ($ids): void {
            foreach (array_keys(config('scm.companies')) as $company) {
                CbeApprovalPolicy::firstOrCreate(['company' => $company], [...$ids, 'version' => 1]);
            }
        });
    }
}
