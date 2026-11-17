<x-filament-panels::page>
@vite('resources/css/scm.css')
<div class="scm-workspace">
    @if($errors->any())<div class="scm-error" role="alert"><h2>Periksa anggaran</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <p role="status" wire:loading.delay>Memuat atau menyimpan anggaran...</p>
    @if($missing)<section class="scm-panel"><p role="alert">Master anggaran belum disiapkan di database. Administrator perlu menjalankan migrasi.</p></section>
    @else
    <section class="scm-panel"><h2>Anggaran utama tahunan</h2><p>Alokasikan anggaran perusahaan ke HO atau project, lalu ke departemen dan COA.</p>
        <p role="status">Master ini belum terhubung ke PR, PO, pembayaran, atau persetujuan kelebihan anggaran. Saldo yang tampil adalah alokasi master, bukan sisa pemakaian.</p>
        <form wire:submit="createBudget"><fieldset wire:loading.attr="disabled"><legend>Tambah anggaran utama</legend><div class="scm-grid">
            <x-scm.select model="budget.company" label="Perusahaan" :options="collect(config('scm.companies'))->mapWithKeys(fn ($item, $code) => [$code => $code])->all()" required />
            <x-scm.field model="budget.year" label="Tahun" type="number" min="2000" max="2100" required />
            <x-scm.select model="budget.currency" label="Mata uang" :options="collect(config('scm.currencies'))->mapWithKeys(fn ($currency) => [$currency => $currency])->all()" required />
            <x-scm.field model="budget.amount" label="Anggaran utama" inputmode="decimal" required help="Tanpa pemisah ribuan; gunakan koma untuk desimal." />
        </div><div class="scm-actions"><x-filament::button type="submit">Simpan anggaran</x-filament::button></div></fieldset></form>
        <div class="scm-scroll" role="region" aria-label="Daftar anggaran utama" tabindex="0"><table><thead><tr><th>Perusahaan</th><th>Tahun</th><th>Anggaran utama</th><th>Tindakan</th></tr></thead><tbody>
        @forelse($budgets as $row)<tr><td>{{ $row->company }}</td><td>{{ $row->year }}</td><td>{{ \App\Services\Scm\Decimal::money($row->amount_minor, $row->currency) }}</td><td><button type="button" wire:click="openBudget({{ $row->id }})">Buka {{ $row->company }} {{ $row->year }} {{ $row->currency }}</button></td></tr>@empty<tr><td colspan="4">Belum ada anggaran utama.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    @if($current)
    <section class="scm-panel"><h2>Alokasi {{ $current->company }} {{ $current->year }} · {{ $current->currency }}</h2>
        <p>Anggaran utama: {{ \App\Services\Scm\Decimal::money($current->amount_minor, $current->currency) }} · Belum dialokasikan ke HO/project: {{ \App\Services\Scm\Decimal::money($current->amount_minor - $current->scopes->sum('amount_minor'), $current->currency) }}</p>
        <div class="scm-actions"><button type="button" wire:click="editAmount('budget', {{ $current->id }})">Ubah anggaran utama</button></div>
        @if($editLevel)<form wire:submit="saveAmount"><fieldset wire:loading.attr="disabled"><legend>Ubah nominal {{ ['budget' => 'anggaran utama', 'scope' => 'HO / project', 'line' => 'departemen / COA'][$editLevel] }}</legend><div class="scm-grid"><x-scm.field model="editAmount" label="Nominal baru ({{ $current->currency }})" inputmode="decimal" required /></div><div class="scm-actions"><x-filament::button type="submit">Simpan perubahan</x-filament::button><button type="button" wire:click="cancelEdit">Batal</button></div></fieldset></form>@endif
        <form wire:submit="createScope"><fieldset wire:loading.attr="disabled"><legend>Tambah alokasi HO / project</legend><div class="scm-grid">
            <x-scm.select model="scope.need_type" label="Jenis kebutuhan" :options="['HO' => 'HO', 'PROJECT' => 'Project']" :live="true" required />
            @if(($scope['need_type'] ?? '') === 'PROJECT')<x-scm.select model="scope.project_numbering_id" label="Project" :options="$projects->mapWithKeys(fn ($p) => [$p->id => $p->code.' · '.$p->name])->all()" required />@endif
            <x-scm.field model="scope.amount" label="Anggaran HO / project ({{ $current->currency }})" inputmode="decimal" required />
        </div><div class="scm-actions"><x-filament::button type="submit">Simpan alokasi</x-filament::button></div></fieldset></form>
        @forelse($current->scopes as $item)<div class="scm-item"><h3>{{ $item->need_type === 'HO' ? 'HO' : ($item->project?->code.' · '.$item->project?->name) }}</h3>
            <p>Anggaran: {{ \App\Services\Scm\Decimal::money($item->amount_minor, $current->currency) }} · Belum dialokasikan ke departemen: {{ \App\Services\Scm\Decimal::money($item->amount_minor - $item->lines->sum('amount_minor'), $current->currency) }}</p>
            <div class="scm-actions"><button type="button" wire:click="editAmount('scope', {{ $item->id }})">Ubah anggaran {{ $item->need_type === 'HO' ? 'HO' : $item->project?->code }}</button></div>
            <div class="scm-scroll" role="region" aria-label="Alokasi departemen dan COA" tabindex="0"><table><thead><tr><th>Departemen</th><th>COA</th><th>Anggaran</th><th>Tindakan</th></tr></thead><tbody>
            @forelse($item->lines as $allocation)<tr><td>{{ $allocation->department->name }}</td><td>{{ $allocation->account->code }} · {{ $allocation->account->name }}</td><td>{{ \App\Services\Scm\Decimal::money($allocation->amount_minor, $current->currency) }}</td><td><button type="button" wire:click="editAmount('line', {{ $allocation->id }})">Ubah {{ $allocation->department->name }} · {{ $allocation->account->code }}</button></td></tr>@empty<tr><td colspan="4">Belum ada alokasi departemen.</td></tr>@endforelse
            </tbody></table></div></div>@empty<p>Belum ada alokasi HO atau project.</p>@endforelse
        <form wire:submit="createLine"><fieldset wire:loading.attr="disabled"><legend>Tambah alokasi departemen dan COA</legend><div class="scm-grid">
            <x-scm.select model="line.scope_id" label="HO / project" :options="$current->scopes->mapWithKeys(fn ($s) => [$s->id => $s->need_type === 'HO' ? 'HO' : ($s->project?->code.' · '.$s->project?->name)])->all()" required />
            <x-scm.select model="line.department_id" label="Departemen" :options="$departments->pluck('name', 'id')" required />
            <x-scm.select model="line.account_id" label="COA" :options="$accounts->mapWithKeys(fn ($a) => [$a->id => $a->code.' · '.$a->name])->all()" required />
            <x-scm.field model="line.amount" label="Anggaran departemen / COA ({{ $current->currency }})" inputmode="decimal" required />
        </div><div class="scm-actions"><x-filament::button type="submit">Simpan alokasi departemen</x-filament::button></div></fieldset></form>
    </section>
    @endif
    @endif
</div>
</x-filament-panels::page>
