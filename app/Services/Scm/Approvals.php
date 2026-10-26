<?php

namespace App\Services\Scm;

use App\Models\Scm\ApprovalRoute;
use App\Models\Scm\Procurement;
use App\Models\Scm\RequestApproval;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Approvals
{
    public const LIMIT_MINOR = 500000000;

    public const LABELS = ['requested' => 'Requested by', 'acknowledged' => 'Acknowledged by', 'crosschecked' => 'Crosschecked by', 'approved' => 'Approved by'];

    public const STATUSES = ['pending' => 'Menunggu', 'signed' => 'Ditandatangani', 'skipped' => 'Dilewati', 'returned' => 'Dikembalikan', 'cancelled' => 'Dibatalkan'];

    public function route(string $company, int $departmentId): ?ApprovalRoute
    {
        return ApprovalRoute::where('company', $company)->where('department_id', $departmentId)->first();
    }

    public function saveRoute(User $actor, array $data): ApprovalRoute
    {
        abort_unless(Access::has($actor, 'scm.configure'), 403);
        $data = Validation::make($data, [
            'company' => ['required', Rule::in(array_keys(config('scm.companies')))],
            'department_id' => 'required|integer|exists:departments,id',
            'acknowledger_id' => 'required|integer|exists:users,id', 'cbo_id' => 'required|integer|exists:users,id', 'cbo3_id' => 'nullable|integer|exists:users,id',
            'version' => 'required|integer|min:0',
        ])->validate();
        foreach (['acknowledger_id', 'cbo_id', 'cbo3_id'] as $key) {
            if (! empty($data[$key]) && ! User::findOrFail($data[$key])->isActiveForAccess()) {
                throw ValidationException::withMessages(['scm' => 'Penandatangan harus memakai akun aktif.']);
            }
        }

        return DB::transaction(function () use ($actor, $data): ApprovalRoute {
            $route = ApprovalRoute::where('company', $data['company'])->where('department_id', $data['department_id'])->lockForUpdate()->first();
            if (($route?->version ?? 0) !== $data['version']) {
                throw ValidationException::withMessages(['scm' => 'Pengaturan persetujuan berubah. Buka ulang sebelum menyimpan.']);
            }
            unset($data['version']);
            $before = $route?->getAttributes();
            $route ??= new ApprovalRoute;
            $route->fill($data);
            $route->version = ($route->version ?? 0) + 1;
            $route->save();
            app(Workflow::class)->audit($actor, $route, 'pengaturan_persetujuan_ho', ['before' => $before, 'after' => $route->getAttributes()]);

            return $route;
        });
    }

    public function start(User $actor, Procurement $request): void
    {
        if ($request->approval_scope !== 'HO') {
            throw ValidationException::withMessages(['scm' => 'Simpan kembali draft untuk pengajuan persetujuan HO.']);
        }
        $route = $this->route($request->company, $request->department_id);
        if (! $route) {
            throw ValidationException::withMessages(['scm' => 'Atasan dan CBO untuk perusahaan dan departemen ini belum diatur di Pengaturan SCM.']);
        }
        $high = $this->requiresCbo3($request);
        if ($high && ! $route->cbo3_id) {
            throw ValidationException::withMessages(['scm' => 'Pengajuan ditandai di atas Rp5 juta. Tetapkan penandatangan COO 3 di Pengaturan SCM sebelum mengajukan.']);
        }
        foreach ([$route->acknowledger_id, $route->cbo_id, ...($high ? [$route->cbo3_id] : [])] as $userId) {
            if (! User::findOrFail($userId)->isActiveForAccess()) {
                throw ValidationException::withMessages(['scm' => 'Akun penandatangan tidak aktif. Perbarui Pengaturan SCM.']);
            }
        }
        $request->approval_round++;
        $request->status = 'approval';
        $request->submitted_at = null;
        foreach ([
            ['requested', $actor->id, true], ['acknowledged', $route->acknowledger_id, true],
            ['crosschecked', $route->cbo_id, $high], ['approved', $high ? $route->cbo3_id : $route->cbo_id, true],
        ] as $position => [$role, $userId, $required]) {
            $skip = $role === 'acknowledged' && (int) $userId === (int) $actor->id;
            $request->approvals()->create([
                'round' => $request->approval_round, 'position' => $position, 'role' => $role, 'user_id' => $userId,
                'is_required' => $required, 'status' => $position === 0 ? 'signed' : ($skip ? 'skipped' : 'pending'),
                'signature_id' => $position === 0 ? $request->prepared_signature_id : null,
                'signed_name' => $position === 0 ? $request->prepared_signed_name : null,
                'acted_at' => $position === 0 ? $request->prepared_signed_at : ($skip ? now() : null),
                'notes' => $skip ? 'Pengaju adalah atasan yang ditetapkan; Acknowledged by dilewati.' : null,
            ]);
        }
        app(Workflow::class)->audit($actor, $request, 'mulai_persetujuan_ho', [
            'round' => $request->approval_round, 'estimated_total_minor' => $request->estimated_total_minor,
            'requires_cbo3' => $high,
            'route_id' => $route->id, 'route_version' => $route->version,
        ], $request->id);
    }

    public function requiresCbo3(Procurement $request): bool
    {
        return $request->requires_cbo3 ?? ($request->estimated_total_minor > self::LIMIT_MINOR);
    }

    public function next(Procurement $request): ?RequestApproval
    {
        return $request->status === 'approval'
            ? $request->approvals()->where('round', $request->approval_round)->where('status', 'pending')->orderBy('position')->first()
            : null;
    }

    public function decide(User $actor, int $id, int $version, int $stepId, string $decision, string $reason = ''): Procurement
    {
        abort_unless($actor->isActiveForAccess(), 403);

        return DB::transaction(function () use ($actor, $id, $version, $stepId, $decision, $reason): Procurement {
            $request = Procurement::lockForUpdate()->findOrFail($id);
            if ($request->status !== 'approval' || $request->version !== $version) {
                throw ValidationException::withMessages(['scm' => 'Data persetujuan sudah berubah. Buka ulang permintaan.']);
            }
            $step = $this->next($request);
            abort_unless($step && (int) $step->id === $stepId && (int) $step->user_id === (int) $actor->id, 403);
            abort_unless(in_array($decision, ['sign', 'skip', 'return'], true), 403);
            if ($decision === 'return') {
                $data = Validation::make(['reason' => $reason], ['reason' => 'required|string|min:10|max:3000'],
                    ['min.string' => ':attribute minimal :min karakter.'], ['reason' => 'Alasan pengembalian untuk revisi'])->validate();
                $step->update(['status' => 'returned', 'acted_at' => now(), 'notes' => $data['reason']]);
                $request->approvals()->where('round', $request->approval_round)->where('status', 'pending')->update(['status' => 'cancelled']);
                $request->status = 'draft';
                $request->prepared_signature_id = null;
                $request->prepared_signed_at = null;
                $request->prepared_signed_name = null;
            } elseif ($decision === 'skip') {
                if ($step->is_required || $step->role !== 'crosschecked') {
                    throw ValidationException::withMessages(['scm' => 'Tahap persetujuan ini wajib ditandatangani.']);
                }
                $step->update(['status' => 'skipped', 'acted_at' => now(), 'notes' => 'CBO melewati pemeriksaan opsional; pengajuan tidak memerlukan COO 3.']);
            } else {
                $signature = app(Signatures::class)->current($actor);
                if (! $signature || ! Storage::disk('scm')->exists($signature->path)) {
                    throw ValidationException::withMessages(['scm' => 'Simpan gambar TTD di profil terlebih dahulu.']);
                }
                $step->update(['status' => 'signed', 'signature_id' => $signature->id, 'signed_name' => $actor->name, 'acted_at' => now()]);
            }
            if ($request->status === 'approval' && ! $this->next($request)) {
                $request->status = 'submitted';
                $request->submitted_at = now();
            }
            $request->version++;
            $request->save();
            app(Workflow::class)->audit($actor, $step, 'keputusan_persetujuan_ho', [
                'decision' => $decision, 'round' => $request->approval_round, 'role' => $step->role,
                'status' => $step->status, 'signature_id' => $step->signature_id, 'acted_at' => $step->acted_at?->toIso8601String(),
            ], $request->id, $decision === 'return' ? $reason : null);

            return $request->fresh();
        }, 3);
    }
}
