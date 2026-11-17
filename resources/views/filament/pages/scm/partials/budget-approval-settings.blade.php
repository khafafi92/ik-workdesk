<div>
    @if(! $budgetApprovalAvailable)
        <p class="scm-error" role="alert">Master persetujuan anggaran belum disiapkan di database. Hubungi administrator.</p>
    @else
        <p>Atur Finance Manager, CBO, dan COO secara berurutan untuk masing-masing perusahaan dan kebutuhan HO atau Project.</p>
        <p role="status">Aturan ini belum aktif untuk PR, PO, atau pembayaran sampai pengendalian anggaran selesai dihubungkan.</p>
        <form wire:submit="saveBudgetApprovalPolicy"><fieldset wire:loading.attr="disabled" wire:target="saveBudgetApprovalPolicy"><legend>{{ $budgetApprovalPolicy['version'] ? 'Edit aturan anggaran' : 'Tambah aturan anggaran' }}</legend>
            <div class="scm-grid">
                <x-scm.select model="budgetApprovalPolicy.company" label="Perusahaan" :options="['APCA' => 'APCA', 'KPMOG' => 'KPMOG']" :disabled="$budgetApprovalPolicy['version'] > 0" required />
                <x-scm.select model="budgetApprovalPolicy.need_type" label="Jenis kebutuhan" :options="['HO' => 'HO', 'PROJECT' => 'Project']" :disabled="$budgetApprovalPolicy['version'] > 0" required />
                <x-scm.select model="budgetApprovalPolicy.finance_manager_id" label="Finance Manager" :options="$poPolicyUsers" required />
                <x-scm.select model="budgetApprovalPolicy.cbo_id" label="CBO" :options="$poPolicyUsers" required />
                <x-scm.select model="budgetApprovalPolicy.coo_id" label="COO" :options="$poPolicyUsers" required />
            </div><div class="scm-actions"><x-filament::button type="submit">Simpan aturan anggaran</x-filament::button><button type="button" wire:click="newBudgetApprovalPolicy">Aturan baru / batal edit</button></div>
        </fieldset></form>
        <div class="scm-scroll" role="region" aria-label="Aturan persetujuan anggaran" tabindex="0"><table><thead><tr><th>Perusahaan / kebutuhan</th><th>Finance Manager</th><th>CBO</th><th>COO</th><th>Tindakan</th></tr></thead><tbody>
            @forelse($budgetApprovalPolicies as $policy)<tr><td>{{ $policy->company }} / {{ $policy->need_type }}</td><td>{{ $policy->financeManager->name }}</td><td>{{ $policy->cbo->name }}</td><td>{{ $policy->coo->name }}</td><td><button type="button" wire:click="editBudgetApprovalPolicy({{ $policy->id }})">Edit {{ $policy->company }} {{ $policy->need_type }}</button></td></tr>
            @empty<tr><td colspan="5">Belum ada aturan persetujuan anggaran.</td></tr>@endforelse
        </tbody></table></div>
    @endif
</div>
