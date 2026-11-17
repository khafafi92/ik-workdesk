<div>
    @if(! $poApprovalAvailable)
    <p class="scm-error" role="alert">Master Approval PO belum dipasang atau diperbarui di database. Hubungi administrator database untuk menjalankan migrasi PO.</p>
    @else
    <p>Atur dua penandatangan buyer per perusahaan. PO dicetak dan ditandatangani basah.</p>
    <form wire:submit="savePoApprovalPolicy">
        <fieldset wire:loading.attr="disabled" wire:target="savePoApprovalPolicy">
            <legend>{{ $poApprovalPolicy['version'] ? 'Edit aturan PO' : 'Tambah aturan PO' }}</legend>
            <div class="scm-grid">
                <x-scm.select model="poApprovalPolicy.company" label="Perusahaan" :options="['APCA'=>'APCA', 'KPMOG'=>'KPMOG']" :disabled="$poApprovalPolicy['version'] > 0" required />
                <x-scm.select model="poApprovalPolicy.buyer_id" label="Penandatangan buyer pertama" :options="$poPolicyUsers" required />
                <x-scm.field model="poApprovalPolicy.buyer_position" label="Jabatan penandatangan pertama" />
                <x-scm.select model="poApprovalPolicy.second_buyer_id" label="Penandatangan buyer kedua" :options="$poPolicyUsers" required />
                <x-scm.field model="poApprovalPolicy.second_buyer_position" label="Jabatan penandatangan kedua" />
            </div>
            <div class="scm-actions"><x-filament::button type="submit">Simpan aturan PO</x-filament::button><button type="button" wire:click="newPoApprovalPolicy">Aturan baru / batal edit</button></div>
        </fieldset>
    </form>
    <div class="scm-scroll" role="region" aria-label="Daftar Master Approval PO" tabindex="0"><table><thead><tr><th>Perusahaan</th><th>Penandatangan pertama</th><th>Penandatangan kedua</th><th>Tindakan</th></tr></thead><tbody>
        @forelse($poApprovalPolicies as $policy)<tr><td>{{ $policy->company }}</td><td>{{ $policy->buyer->name }}<br>{{ $policy->buyer_position ?: '-' }}</td><td>{{ $policy->secondBuyer?->name ?? 'Belum diatur' }}<br>{{ $policy->second_buyer_position ?: '-' }}</td><td><button type="button" wire:click="editPoApprovalPolicy({{ $policy->id }})">Edit {{ $policy->company }}</button></td></tr>
        @empty<tr><td colspan="4">Belum ada aturan PO.</td></tr>@endforelse
    </tbody></table></div>
    @endif
</div>
