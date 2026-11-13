<x-filament-panels::page>
    @vite('resources/css/scm.css')
    <div class="scm-workspace">
        @if($errors->any())<div class="scm-error" role="alert"><h2>Periksa data COA</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <p role="status" wire:loading.delay>Memuat atau menyimpan data COA...</p>
        @if($missing)
            <section class="scm-panel"><p role="alert">Master COA belum disiapkan di database. Administrator perlu menjalankan migrasi.</p></section>
        @elseif($loadError)
            <section class="scm-panel"><p role="alert">Data COA belum dapat dimuat. Coba lagi atau hubungi administrator.</p><button type="button" wire:click="$refresh">Coba lagi</button></section>
        @else
            <section class="scm-panel"><header class="scm-heading"><div><h2>Master COA</h2><p>Kelola kode akun untuk setiap perusahaan.</p></div><button type="button" wire:click="newAccount">Tambah COA</button></header>
                <x-scm.field model="search" label="Cari kode atau nama akun" :live="true" />
                <div class="scm-scroll" role="region" aria-label="Daftar COA" tabindex="0"><table><thead><tr><th scope="col">Perusahaan</th><th scope="col">Kode COA</th><th scope="col">Nama akun</th><th scope="col">Induk</th><th scope="col">Tingkat</th><th scope="col">Status</th><th scope="col">Tindakan</th></tr></thead><tbody>
                    @forelse($accounts as $row)<tr wire:key="coa-{{ $row->id }}"><td>{{ $row->company }}</td><td>{{ $row->code }}</td><td>{{ $row->name }}</td><td>{{ $row->parent ? $row->parent->code.' · '.$row->parent->name : 'Akun induk' }}</td><td>{{ $row->parent ? ($row->parent->parent ? 3 : 2) : 1 }}</td><td>{{ $row->is_active ? 'Aktif' : 'Nonaktif' }}</td><td><button type="button" wire:click="editAccount({{ $row->id }})">Edit {{ $row->code }}</button></td></tr>
                    @empty<tr><td colspan="7">Belum ada COA yang sesuai. Tambahkan akun sesuai daftar Finance.</td></tr>@endforelse
                </tbody></table></div>{{ $accounts->links() }}
            </section>
            <section class="scm-panel"><h2>{{ $accountId ? 'Edit COA' : 'Tambah COA' }}</h2>
                <form wire:submit="saveAccount"><fieldset wire:loading.attr="disabled" wire:target="saveAccount"><legend>Data akun</legend><div class="scm-grid">
                    <x-scm.select model="account.company" label="Perusahaan" :options="collect(config('scm.companies'))->mapWithKeys(fn ($item, $code) => [$code => $code])->all()" :live="true" required />
                    <x-scm.field model="account.code" label="Kode COA" required />
                    <x-scm.field model="account.name" label="Nama akun" required />
                    <x-scm.select model="account.parent_id" label="Induk COA" placeholder="Tanpa induk (tingkat 1)" :options="$parents->mapWithKeys(fn ($a) => [$a->id => $a->code.' · '.$a->name])->all()" help="Kosongkan untuk akun induk. Maksimal tiga tingkat; akun pada semua tingkat dapat digunakan." />
                    <x-scm.check model="account.is_active" label="Akun aktif" />
                </div><div class="scm-actions"><x-filament::button type="submit" wire:loading.attr="disabled" wire:target="saveAccount">Simpan COA</x-filament::button>@if($accountId)<button type="button" wire:click="newAccount">Batal edit</button>@endif</div></fieldset></form>
            </section>
        @endif
    </div>
</x-filament-panels::page>
