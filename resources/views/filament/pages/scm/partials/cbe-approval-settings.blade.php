<div id="cbe-approval-settings">
    <p>Tetapkan penandatangan CBE per perusahaan. Prepared by otomatis memakai akun SCM yang menandatangani dan mengajukan CBE. Acknowledged, Crosschecked, dan Approved by wajib menggunakan akun berbeda.</p>
    <form wire:submit="saveCbeApprovalPolicy">
        <fieldset wire:loading.attr="disabled" wire:target="saveCbeApprovalPolicy">
            <legend>{{ $cbeApprovalPolicy['version'] ? 'Edit aturan CBE' : 'Tambah aturan CBE' }}</legend>
            <div class="scm-grid">
                <x-scm.select model="cbeApprovalPolicy.company" label="Perusahaan" :options="['APCA'=>'APCA', 'KPMOG'=>'KPMOG']" :disabled="$cbeApprovalPolicy['version'] > 0" required />
                <p>Prepared By: akun SCM yang login saat mengajukan CBE.</p>
                <x-scm.select model="cbeApprovalPolicy.acknowledger_id" label="Acknowledged By" :options="$approvalUsers" required />
                <x-scm.select model="cbeApprovalPolicy.crosschecker_id" label="Crosschecked By" :options="$approvalUsers" required />
                <x-scm.select model="cbeApprovalPolicy.approver_id" label="Approved By" :options="$approvalUsers" required />
            </div>
            <div class="scm-actions"><x-filament::button type="submit">Simpan master approval</x-filament::button><button type="button" wire:click="newCbeApprovalPolicy">Master baru / batal edit</button></div>
        </fieldset>
    </form>
    <p role="status" wire:loading wire:target="saveCbeApprovalPolicy">Menyimpan aturan CBE...</p>
    <div class="scm-scroll" role="region" aria-label="Aturan CBE pada Master Approval" tabindex="0">
        <table><thead><tr><th>Perusahaan</th><th>Prepared By</th><th>Acknowledged By</th><th>Crosschecked By</th><th>Approved By</th><th>Tindakan</th></tr></thead><tbody>
        @forelse($cbeApprovalPolicies as $policy)
            <tr><td>{{ $policy->company }}</td><td>Akun SCM pengaju</td><td>{{ $policy->acknowledger->name }}</td><td>{{ $policy->crosschecker->name }}</td><td>{{ $policy->approver->name }}</td><td><button type="button" wire:click="editCbeApprovalPolicy({{ $policy->id }})" aria-label="Edit aturan CBE {{ $policy->company }}">Edit</button></td></tr>
        @empty<tr><td colspan="6">Belum ada aturan CBE. Tetapkan penandatangan sebelum SCM mengajukan CBE baru.</td></tr>@endforelse
        </tbody></table>
    </div>
</div>
