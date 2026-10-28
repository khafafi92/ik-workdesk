<?php

namespace App\Services\Scm;

use App\Models\Scm\Cbe;
use App\Models\Scm\CbeApproval;
use App\Models\Scm\CbeApprovalPolicy;
use App\Models\Scm\Procurement;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CbeApprovals
{
    public const LABELS = ['prepared' => 'Prepared By:', 'acknowledged' => 'Acknowledged By:', 'crosschecked' => 'Crosschecked By:', 'approved' => 'Approved By:'];

    public static function defaults(): array
    {
        return ['company' => 'KPMOG', 'acknowledger_id' => '', 'crosschecker_id' => '', 'approver_id' => '', 'version' => 0];
    }

    public function savePolicy(User $actor, array $input): CbeApprovalPolicy
    {
        abort_unless(Access::has($actor, 'scm.configure'), 403);
        $data = Validation::make($input, [
            'company' => ['required', Rule::in(array_keys(config('scm.companies')))],
            'acknowledger_id' => 'required|integer|exists:users,id',
            'crosschecker_id' => 'required|integer|exists:users,id',
            'approver_id' => 'required|integer|exists:users,id',
            'version' => 'required|integer|min:0',
        ])->validate();
        $users = User::whereIn('id', [$data['acknowledger_id'], $data['crosschecker_id'], $data['approver_id']])->get();
        $this->reject($users->count() !== 3, 'Ketiga penandatangan CBE harus memakai akun yang berbeda.');
        $this->reject($users->contains(fn ($user) => ! $user->isActiveForAccess()), 'Penandatangan CBE harus memakai akun aktif.');

        try {
            return DB::transaction(function () use ($actor, $data): CbeApprovalPolicy {
                $policy = CbeApprovalPolicy::where('company', $data['company'])->lockForUpdate()->first();
                $this->reject(($policy?->version ?? 0) !== (int) $data['version'], 'Aturan CBE pada Master Approval berubah atau sudah ada. Buka ulang master sebelum menyimpan.');
                $before = $policy?->getAttributes();
                $policy ??= new CbeApprovalPolicy;
                $policy->fill(collect($data)->except('version')->all());
                $policy->version = ($policy->version ?? 0) + 1;
                $policy->save();
                app(Workflow::class)->audit($actor, $policy, 'master_approval_cbe', ['before' => $before, 'after' => $policy->getAttributes()]);

                return $policy;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['scm' => 'Aturan CBE pada Master Approval berubah atau sudah ada. Buka ulang master sebelum menyimpan.']);
        }
    }

    public function start(User $actor, Cbe $cbe): void
    {
        $request = $cbe->request;
        abort_unless(Access::manages($actor, $request), 403);
        $this->reject((int) $cbe->prepared_by !== (int) $actor->id, 'Prepared by harus ditandatangani akun SCM yang menyiapkan CBE ini.');
        $policy = CbeApprovalPolicy::with(['acknowledger', 'crosschecker', 'approver'])->where('company', $request->company)->lockForUpdate()->first();
        $this->reject(! $policy, 'Aturan CBE untuk perusahaan ini belum diatur. Buka Pengaturan SCM > Master Approval, pilih CBE, lalu tetapkan Acknowledged, Crosschecked, dan Approved by.');
        $this->reject($cbe->approvals()->exists(), 'Persetujuan CBE sudah dimulai.');
        $users = [$actor, $policy->acknowledger, $policy->crosschecker, $policy->approver];
        $this->reject(collect($users)->pluck('id')->unique()->count() !== 4, 'Prepared by dan ketiga penandatangan CBE harus memakai akun berbeda.');
        $this->reject(collect($users)->contains(fn ($user) => ! $user->isActiveForAccess()), 'Akun penandatangan CBE tidak aktif. Perbarui master terlebih dahulu.');
        $signature = app(Signatures::class)->current($actor);
        $this->reject(! $signature || ! Storage::disk('scm')->exists($signature->path), 'Simpan gambar TTD pada profil SCM sebelum menandatangani dan mengajukan CBE.');

        foreach (array_keys(self::LABELS) as $position => $role) {
            $user = $users[$position];
            $cbe->approvals()->create([
                'position' => $position, 'role' => $role, 'user_id' => $user->id, 'assigned_name' => $user->name,
                'status' => $position === 0 ? 'signed' : 'pending',
                'signature_id' => $position === 0 ? $signature->id : null,
                'signed_name' => $position === 0 ? $actor->name : null, 'acted_at' => $position === 0 ? now() : null,
            ]);
        }
        app(Workflow::class)->audit($actor, $cbe, 'mulai_approval_cbe', ['policy_id' => $policy->id, 'policy_version' => $policy->version], $request->id);
    }

    public function next(Cbe $cbe): ?CbeApproval
    {
        return $cbe->approvals()->where('status', 'pending')->first();
    }

    public function pendingCount(User $actor): int
    {
        if (! $actor->isActiveForAccess()) {
            return 0;
        }

        return CbeApproval::where('user_id', $actor->id)->where('status', 'pending')
            ->whereHas('cbe', fn ($query) => $query->where('status', 'final')->whereHas('request', fn ($request) => $request->where('status', 'selected')))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('scm_cbe_approvals as earlier')
                ->whereColumn('earlier.cbe_id', 'scm_cbe_approvals.cbe_id')->whereColumn('earlier.position', '<', 'scm_cbe_approvals.position')->where('earlier.status', 'pending'))
            ->count();
    }

    public function sign(User $actor, int $id, int $requestVersion): Cbe
    {
        abort_unless($actor->isActiveForAccess(), 403);

        return DB::transaction(function () use ($actor, $id, $requestVersion): Cbe {
            $candidate = Cbe::findOrFail($id);
            $request = Procurement::lockForUpdate()->findOrFail($candidate->request_id);
            $cbe = Cbe::lockForUpdate()->findOrFail($id);
            abort_unless($cbe->approvals()->where('user_id', $actor->id)->where('role', '!=', 'prepared')->exists(), 403);
            $this->reject($request->version !== $requestVersion, 'Data CBE berubah. Buka ulang sebelum menandatangani.');
            $step = $this->next($cbe);
            abort_unless($step && (int) $step->user_id === (int) $actor->id, 403);
            $this->reject($cbe->status !== 'final' || $request->status !== 'selected', 'CBE tidak sedang menunggu persetujuan.');
            $signature = app(Signatures::class)->current($actor);
            $this->reject(! $signature || ! Storage::disk('scm')->exists($signature->path), 'Simpan gambar TTD pada profil terlebih dahulu.');
            $step->update(['status' => 'signed', 'signature_id' => $signature->id, 'signed_name' => $actor->name, 'acted_at' => now()]);
            $request->version++;
            $request->save();
            app(Workflow::class)->audit($actor, $cbe, 'tanda_tangan_cbe', ['role' => $step->role, 'signature_id' => $signature->id, 'signed_name' => $actor->name], $request->id);

            return $cbe->fresh();
        });
    }

    public function complete(Cbe $cbe): bool
    {
        $steps = $cbe->approvals()->get();
        if ($steps->isEmpty()) {
            return ! ($cbe->snapshot['approval_required'] ?? false);
        }

        return $steps->count() === 4 && $steps->every(fn ($step) => $step->status === 'signed');
    }

    public function startExisting(User $actor, int $id, int $version): Procurement
    {
        return DB::transaction(function () use ($actor, $id, $version): Procurement {
            $request = Procurement::lockForUpdate()->findOrFail($id);
            abort_unless(Access::manages($actor, $request), 403);
            $this->reject($request->version !== $version, 'Data berubah. Buka ulang pengadaan sebelum mengajukan tanda tangan CBE.');
            $cbe = $request->cbe()->lockForUpdate()->first();
            $this->reject($request->status !== 'selected' || ! $cbe || $cbe->status !== 'final', 'Hanya CBE final yang belum masuk legalitas yang dapat diajukan.');
            $this->start($actor, $cbe);
            $request->version++;
            $request->save();

            return $request->fresh();
        });
    }

    public function canView(User $actor, Cbe $cbe): bool
    {
        return $actor->isActiveForAccess() && (Access::views($actor, $cbe->request) || $cbe->approvals()->where('user_id', $actor->id)->exists());
    }

    private function reject(bool $condition, string $message): void
    {
        if ($condition) {
            throw ValidationException::withMessages(['scm' => $message]);
        }
    }
}
