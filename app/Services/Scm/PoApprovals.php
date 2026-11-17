<?php

namespace App\Services\Scm;

use App\Models\Scm\PoApprovalPolicy;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PoApprovals
{
    public static function defaults(): array
    {
        return ['company' => 'KPMOG', 'buyer_id' => '', 'buyer_position' => '', 'second_buyer_id' => '', 'second_buyer_position' => '', 'version' => 0];
    }

    public function savePolicy(User $actor, array $input): PoApprovalPolicy
    {
        abort_unless(Access::has($actor, 'scm.configure'), 403);
        if (! Schema::hasTable('scm_po_approval_policies') || ! Schema::hasColumn('scm_po_approval_policies', 'second_buyer_id')) {
            throw ValidationException::withMessages(['scm' => 'Master Approval PO belum dipasang di database. Hubungi administrator database.']);
        }
        $data = Validation::make($input, ['company' => ['required', Rule::in(array_keys(config('scm.companies')))],
            'buyer_id' => 'required|integer|exists:users,id', 'buyer_position' => 'nullable|string|max:255',
            'second_buyer_id' => 'required|integer|different:buyer_id|exists:users,id', 'second_buyer_position' => 'nullable|string|max:255',
            'version' => 'required|integer|min:0'])->validate();
        if (User::whereIn('id', [$data['buyer_id'], $data['second_buyer_id']])->get()->contains(fn ($user) => ! $user->isActiveForAccess())) {
            throw ValidationException::withMessages(['scm' => 'Penandatangan buyer harus menggunakan akun aktif.']);
        }
        try {
            return DB::transaction(function () use ($actor, $data): PoApprovalPolicy {
                $policy = PoApprovalPolicy::where('company', $data['company'])->lockForUpdate()->first();
                if (($policy?->version ?? 0) !== (int) $data['version']) {
                    throw ValidationException::withMessages(['scm' => 'Master Approval PO berubah. Buka ulang sebelum menyimpan.']);
                }
                $before = $policy?->getAttributes();
                $policy ??= new PoApprovalPolicy;
                $policy->fill(collect($data)->except('version')->all());
                $policy->version = ($policy->version ?? 0) + 1;
                $policy->save();
                app(Workflow::class)->audit($actor, $policy, 'master_approval_po', ['before' => $before, 'after' => $policy->getAttributes()]);

                return $policy;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['scm' => 'Master Approval PO sudah berubah. Buka ulang sebelum menyimpan.']);
        }
    }
}
