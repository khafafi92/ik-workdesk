<section class="scm-panel" id="master-approval" aria-labelledby="scm-approval-master-title">
    <h2 id="scm-approval-master-title">Master Approval PR/SR</h2>
    <p>Atur penandatangan per perusahaan, divisi, jenis kebutuhan, dan PR/SR. Kebutuhan HO dan Project memakai master terpisah. CBO hanya untuk HO. Perubahan berlaku saat pengajuan berikutnya diajukan.</p>
    <p class="scm-help">Pada HO, CBO tetap wajib menyetujui. Jika Approved by diisi pejabat selain CBO, pilih CBO pada Crosschecked by; tahap tersebut wajib.</p>
    <form wire:submit="saveApprovalPolicy">
        <fieldset wire:loading.attr="disabled" wire:target="saveApprovalPolicy">
            <legend>{{ ($approvalPolicy['version'] ?? 0) ? 'Edit master approval' : 'Tambah master approval' }}</legend>
            <div class="scm-grid">
                <x-scm.select model="approvalPolicy.company" label="Perusahaan" :options="['APCA'=>'APCA','KPMOG'=>'KPMOG']" :disabled="($approvalPolicy['version'] ?? 0) > 0" required />
                <x-scm.select model="approvalPolicy.department_id" label="Divisi / departemen" :options="$departments" :disabled="($approvalPolicy['version'] ?? 0) > 0" required />
                <x-scm.select model="approvalPolicy.need_type" label="Jenis kebutuhan" :options="\App\Services\Scm\ApprovalPolicies::NEED_TYPES" :live="true" :disabled="($approvalPolicy['version'] ?? 0) > 0" required />
                <x-scm.select model="approvalPolicy.request_kind" label="Berlaku untuk" :options="\App\Services\Scm\ApprovalPolicies::KINDS" :disabled="($approvalPolicy['version'] ?? 0) > 0" required />
                <x-scm.select model="approvalPolicy.supervisor_id" label="Persetujuan SPV (jika ada)" :options="$policyUsers" help="Persetujuan di sistem sebelum Acknowledged by; tidak menambah kolom pada kertas." />
                <x-scm.select model="approvalPolicy.acknowledger_id" label="Acknowledged by" :options="$policyUsers" help="Pilih atasan atau Manager. Kosongkan jika tahap ini tidak diperlukan." />
                <x-scm.select model="approvalPolicy.crosschecker_id" label="Crosschecked by" :options="$policyUsers" :live="true" help="Kosongkan jika tidak ada Crosschecked by; kolom pada form akan kosong." />
                <x-scm.select model="approvalPolicy.approver_id" label="Approved by" :options="$policyUsers" required />
                @if(filled($approvalPolicy['crosschecker_id'] ?? null))
                    <x-scm.select model="approvalPolicy.crosscheck_mode" label="Ketentuan Crosschecked by" :options="\App\Services\Scm\ApprovalPolicies::CROSSCHECK_MODES" required />
                @endif
            </div>
            <x-scm.check model="approvalPolicy.use_amount_escalation" label="Approved by berbeda untuk pengajuan di atas Rp5 juta" :live="true" />
            @if($approvalPolicy['use_amount_escalation'] ?? false)
                <x-scm.select model="approvalPolicy.high_approver_id" label="Approved by untuk pengajuan di atas Rp5 juta" :options="$policyUsers" help="Jika belum diisi, pengajuan yang ditandai di atas Rp5 juta ditahan sampai penandatangan ditetapkan." />
            @endif
            @if($approvalPolicyError)<p class="scm-error" role="alert">{{ $approvalPolicyError }}</p>@endif
            <div class="scm-actions"><x-filament::button type="submit" wire:loading.attr="disabled">Simpan master approval</x-filament::button><button type="button" wire:click="newApprovalPolicy">Master baru / batal edit</button></div>
        </fieldset>
    </form>
    <p role="status" wire:loading wire:target="saveApprovalPolicy">Menyimpan master approval...</p>
    <p class="scm-help">Master khusus PR atau SR didahulukan dari master PR dan SR pada perusahaan, divisi, dan jenis kebutuhan yang sama. Jika pembuat juga Acknowledged by, tahap tersebut tidak ditandatangani sendiri.</p>
    <div class="scm-scroll" role="region" aria-label="Daftar master approval" tabindex="0">
        <table><thead><tr><th>Perusahaan / divisi</th><th>Kebutuhan / form</th><th>SPV</th><th>Acknowledged by</th><th>Crosschecked by</th><th>Approved by</th><th>Tindakan</th></tr></thead><tbody>
        @forelse($approvalPolicies as $policy)
            <tr><td>{{ $policy->company }}<br>{{ $policy->department->name }}</td><td>{{ \App\Services\Scm\ApprovalPolicies::NEED_TYPES[$policy->need_type] }}<br>{{ \App\Services\Scm\ApprovalPolicies::KINDS[$policy->request_kind] }}</td>
                <td>{{ $policy->supervisor?->name ?? 'Kosong' }}</td><td>{{ $policy->acknowledger?->name ?? 'Kosong' }}</td>
                <td>{{ $policy->crosschecker?->name ?? 'Kosong' }}@if($policy->crosschecker)<br>{{ \App\Services\Scm\ApprovalPolicies::CROSSCHECK_MODES[$policy->crosscheck_mode] }}@endif</td>
                <td>{{ $policy->approver->name }}@if($policy->use_amount_escalation)<br>Di atas Rp5 juta: {{ $policy->highApprover?->name ?? 'belum ditetapkan' }}@endif</td>
                <td><button type="button" wire:click="editApprovalPolicy({{ $policy->id }})" aria-label="Edit {{ $policy->company }} {{ $policy->department->name }} {{ $policy->need_type }} {{ $policy->request_kind }}">Edit</button> <button type="button" wire:click="copyApprovalPolicy({{ $policy->id }})" aria-label="Salin master #{{ $policy->id }}">Salin</button></td></tr>
        @empty<tr><td colspan="7">Belum ada master approval. Tambahkan aturan HO atau Project untuk divisi yang akan mengajukan PR/SR.</td></tr>@endforelse
        </tbody></table>
    </div>
</section>
