<?php

namespace App\Services\Scm;

use App\Models\Scm\Vendor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorDeletion
{
    public function delete(User $actor, int $id, int $version): void
    {
        abort_unless(Access::manages($actor), 403);
        DB::transaction(function () use ($actor, $id, $version): void {
            $vendor = Vendor::query()->lockForUpdate()->findOrFail($id);
            if ($vendor->version !== $version || $vendor->archived_at !== null) {
                throw ValidationException::withMessages(['scm' => 'Profil vendor berubah. Muat ulang sebelum menghapus.']);
            }
            $active = DB::table('scm_candidates')->join('scm_requests', 'scm_requests.id', '=', 'scm_candidates.request_id')
                ->where('scm_candidates.vendor_id', $id)->whereNotIn('scm_requests.status', ['completed', 'cancelled'])->exists();
            if ($active) {
                throw ValidationException::withMessages(['scm' => 'Vendor masih digunakan dalam pengadaan yang berjalan. Selesaikan pengadaan sebelum menghapus vendor.']);
            }
            $vendor->is_active = false;
            $vendor->archived_at = now();
            $vendor->updated_by = $actor->id;
            $vendor->version++;
            $vendor->save();
            app(Workflow::class)->audit($actor, $vendor, 'hapus_vendor', ['code' => $vendor->code, 'name' => $vendor->name]);
        });
    }
}
