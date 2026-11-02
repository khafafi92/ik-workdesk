<x-filament-panels::page>
    <div class="scm-workspace">
        <p role="status" wire:loading.delay>Memuat atau menyimpan nomor PR/SR. Mohon tunggu...</p>
        @if($errors->any())<div class="scm-error" role="alert"><h2>Periksa data berikut</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <section class="scm-panel scm-number-register" aria-label="Riwayat nomor PR dan SR">
            <h2>Nomor terpakai dan keterangan</h2>
            <p>Daftar mengikuti akses permintaan Anda. Nomor terbit saat membuat form dan tetap tercatat meskipun draft belum disimpan.</p>
            <div class="scm-grid scm-register-filters">
                <x-scm.field model="search" label="Cari nomor atau keterangan" :live="true" />
                <x-scm.select model="needType" label="Jenis kebutuhan" :options="['HO' => 'HO', 'PROJECT' => 'Project']" :live="true" />
                <x-scm.select model="kind" label="Jenis permintaan" :options="['PR' => 'PR', 'SR' => 'SR']" :live="true" />
                <x-scm.select model="company" label="Perusahaan" :options="['APCA' => 'APCA', 'KPMOG' => 'KPMOG']" :live="true" />
                <x-scm.select model="usage" label="Pemakaian nomor" :options="['used' => 'Terpakai', 'reserved' => 'Terbit, draft belum disimpan']" placeholder="Semua nomor" :live="true" />
            </div>
            <div class="scm-actions"><button type="button" wire:click="clearFilters">Hapus semua filter</button></div>
            <div class="scm-scroll scm-register-scroll" role="region" aria-label="Daftar nomor PR/SR" tabindex="0">
                <table class="scm-register-table">
                    <colgroup><col class="scm-register-row-number"><col class="scm-register-document"><col class="scm-register-company"><col class="scm-register-kind"><col class="scm-register-need"><col class="scm-register-project"><col class="scm-register-person"><col class="scm-register-department"><col class="scm-register-date"><col class="scm-register-description"><col class="scm-register-status"></colgroup>
                    <thead>
                        <tr class="scm-register-letters" aria-hidden="true"><th></th>@foreach(range('A', 'J') as $letter)<th>{{ $letter }}</th>@endforeach</tr>
                        <tr class="scm-register-headings"><th scope="col">No.</th><th scope="col">Nomor PR/SR</th><th scope="col">Perusahaan</th><th scope="col">Jenis</th><th scope="col">Kebutuhan</th><th scope="col">Project</th><th scope="col">Pembuat</th><th scope="col">Departemen</th><th scope="col">Tanggal terbit</th><th scope="col">Keterangan</th><th scope="col">Status</th></tr>
                    </thead><tbody>
                    @forelse($numbers as $number)
                        <tr wire:key="request-number-{{ $number->id }}">
                            <th scope="row">{{ $numbers->firstItem() + $loop->index }}</th>
                            <td class="scm-register-number">{{ $number->number }}</td>
                            <td>{{ $number->company }}</td>
                            <td>{{ $number->kind }}</td>
                            <td>{{ $number->need_type === 'PROJECT' ? 'Project' : 'HO' }}</td>
                            <td>{{ $number->projectNumbering?->name ?? $number->request?->project?->name ?? '-' }}</td>
                            <td>{{ $number->requester->name }}</td>
                            <td>{{ $number->request?->department?->name ?? '-' }}</td>
                            <td class="scm-register-timestamp">{{ $number->created_at?->format('d/m/Y H:i') }}</td>
                            <td>@if($number->request)<div>{{ $number->request->title }}</div>@if(filled($number->request->reason))<div>{{ $number->request->reason }}</div>@endif @else Draft belum disimpan. @endif</td>
                            <td>{{ $number->request ? ($statuses[$number->request->status] ?? $number->request->status) : 'Terbit, belum terpakai' }}</td>
                        </tr>
                    @empty<tr><td colspan="11">Belum ada nomor sesuai filter dan akses Anda.</td></tr>@endforelse
                </tbody></table>
            </div>
            <p class="scm-help">Geser tabel ke samping untuk melihat semua kolom. Header kolom dan nomor baris tetap terlihat saat tabel digulir.</p>
            {{ $numbers->links() }}
        </section>
        @if($configure)
            <section class="scm-panel" aria-label="Master kode Project">
                <h2>Master kode Project</h2>
                <p>Buat kode Project satu kali di master ini. Saat membuat PR/SR, pilih master tersebut untuk memakai kodenya pada nomor dokumen.</p>
                <p>Format kode Project KPMOG: <strong>SINGKATAN/TAHUN AWARDED/JENIS PEKERJAAN/NOMOR URUT</strong>. Contoh: UBAD/23/SPC/001. Kode lama seperti COMP-19E001 tetap dapat digunakan.</p>
                <form wire:submit="saveMaster">
                    <fieldset wire:loading.attr="disabled" wire:target="saveMaster" class="scm-grid">
                        <legend>{{ $masterId ? 'Edit master nomor Project' : 'Tambah master nomor Project' }}</legend>
                        <x-scm.select model="master.format" label="Format kode Project" :options="['standard' => 'Format KPMOG', 'legacy' => 'Kode lama / khusus']" :live="true" required />
                        @if(($master['format'] ?? 'standard') === 'standard')
                            <x-scm.field model="master.project_prefix" label="Singkatan Project" :live="true" required maxlength="60" help="Empat huruf singkatan Project, misalnya UBAD. Pembeda dapat ditambahkan seperti BMTH-LNG atau BMTH-BBM pada daftar Rev.4." />
                            <x-scm.field model="master.awarded_year" label="Tahun awarded" type="number" :live="true" required min="2000" max="2099" help="Isi empat digit tahun. Kode memakai dua digit terakhir." />
                            <x-scm.select model="master.work_code" label="Jenis pekerjaan" :options="\App\Services\Scm\ProjectCode::WORK_CODES" :live="true" required />
                            <x-scm.field model="master.running_number" label="Nomor urut Project" :live="true" required inputmode="numeric" help="Isi 1 sampai 999 sesuai register Project. Angka 1 akan ditulis sebagai 001. Ini berbeda dari nomor urut PR/SR." />
                        @else
                            <x-scm.field model="master.code" label="Kode lama / khusus" :live="true" required help="Gunakan kode yang sudah ditetapkan, misalnya COMP-19E001 untuk LTRO. Kode berisi huruf, angka, dan tanda hubung." />
                        @endif
                        <x-scm.field model="master.name" label="Nama Project" required />
                        <x-scm.check model="master.is_active" label="Aktif untuk nomor baru" />
                    </fieldset>
                    <p>Kode Project yang akan disimpan: <output id="scm-project-code-preview" aria-live="polite"><strong>{{ $projectCodePreview ?? 'Lengkapi komponen kode Project.' }}</strong></output></p>
                    <p class="scm-help">Kode yang sudah menerbitkan nomor PR/SR tidak dapat diganti. Nama dan status aktif masih dapat diperbarui.</p>
                    <div class="scm-actions"><button type="submit" wire:loading.attr="disabled" wire:target="saveMaster">Simpan master</button><button type="button" wire:click="newMaster">Kosongkan form</button></div>
                </form>
                <div class="scm-scroll" role="region" aria-label="Daftar master nomor Project" tabindex="0">
                    <table class="scm-number-table"><thead><tr><th scope="col">Kode Project</th><th scope="col">Nama Project</th><th scope="col">Status</th><th scope="col">Tindakan</th></tr></thead><tbody>
                        @forelse($masters as $row)<tr wire:key="number-master-{{ $row->id }}"><td data-label="Kode Project"><div>{{ $row->code }}</div></td><td data-label="Nama Project"><div>{{ $row->name }}</div></td><td data-label="Status"><div>{{ $row->is_active ? 'Aktif' : 'Nonaktif' }}</div></td><td data-label="Tindakan"><div><button type="button" wire:click="editMaster({{ $row->id }})" aria-label="Edit master {{ $row->code }}">Edit master</button></div></td></tr>
                        @empty<tr><td colspan="4">Master nomor Project belum tersedia. Tambahkan kode dan nama Project melalui form di atas.</td></tr>@endforelse
                    </tbody></table>
                </div>
                {{ $masters->links() }}
            </section>
        @endif
    </div>
</x-filament-panels::page>
