<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $accesses = DB::table('scm_vendor_portal_accesses as access')
                ->join('scm_vendors as vendor', 'vendor.id', '=', 'access.vendor_id')
                ->where('vendor.code', 'like', 'WEB-%')
                ->whereNotNull('access.access_code')
                ->select('vendor.id', 'access.access_code')->get();

            if ($accesses->groupBy('id')->contains(fn ($group) => $group->count() > 1)) {
                throw new RuntimeException('Vendor memiliki lebih dari satu akses portal. Periksa akses sebelum melanjutkan migrasi.');
            }
            foreach ($accesses as $access) {
                $code = Crypt::decryptString($access->access_code);
                if (DB::table('scm_vendors')->where('code', $code)->where('id', '!=', $access->id)->exists()) {
                    throw new RuntimeException('Vendor ID sudah digunakan oleh kode master vendor lain. Periksa benturan sebelum melanjutkan migrasi.');
                }
                DB::table('scm_vendors')->where('id', $access->id)->update(['code' => $code, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
            }
        });
    }

    public function down(): void {}
};
