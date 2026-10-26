<section class="scm-panel"><h2>Pengaturan master SCM</h2><p>Jenis dokumen mencakup legalitas Form 005, KTP, NPWP, NIB, dan SBU. Form 005 memiliki kelengkapan perusahaan serta persyaratan bersyaratnya sendiri; checklist pengadaan tetap mengikuti persyaratan kategori/proses. Mengubah master tidak mengubah checklist yang sudah dimulai.</p>
<form wire:submit="saveMaster"><h3>{{ empty($master['id']) ? 'Tambah pengaturan master' : 'Edit pengaturan master' }}</h3><div class="scm-grid">
    <x-scm.select model="master.kind" label="Master yang dikelola" :options="['category'=>'Kategori pengadaan','type'=>'Jenis dokumen','requirement'=>'Persyaratan kategori']" :live="true" required />
    @if($master['kind'] === 'requirement')
        <x-scm.select model="master.category_id" label="Kategori persyaratan" :options="$categories->pluck('name','id')" required /><x-scm.select model="master.document_type_id" label="Jenis dokumen persyaratan" :options="$types->pluck('name','id')" required /><x-scm.check model="master.is_required" label="Wajib pada kategori ini" />
    @else
        <x-scm.field model="master.name" label="Nama master" required /><x-scm.check model="master.is_active" label="Master aktif" />
        @if($master['kind'] === 'type')<x-scm.field model="master.code" label="Kode jenis dokumen" required /><x-scm.field model="master.description" label="Deskripsi dokumen" multiline /><x-scm.check model="master.has_expiry" label="Tanggal kedaluwarsa relevan" />@endif
    @endif
</div><p>{{ empty($master['id']) ? 'Membuat master atau menyimpan konfigurasi kategori.' : 'Mengedit master #'.$master['id'] }}</p><div class="scm-actions"><x-filament::button type="submit" wire:loading.attr="disabled" wire:target="saveMaster">Simpan pengaturan</x-filament::button><button type="button" wire:click="newMaster('{{ $master['kind'] }}')">Batal / buat master baru</button></div></form>
<div class="scm-grid"><section class="scm-item"><h3>Kategori pengadaan</h3>@forelse($categories as $category)<p>{{ $category->name }} · {{ $category->is_active ? 'Aktif' : 'Nonaktif' }} <button type="button" wire:click="editMaster('category',{{ $category->id }})">Edit kategori</button></p>@empty<p>Belum ada kategori. Kategori dapat digunakan untuk mengatur persyaratan dokumen; PR/SR baru tetap dapat diproses tanpa kategori.</p>@endforelse</section>
<section class="scm-item"><h3>Jenis dokumen</h3>@foreach($types as $type)<p>{{ $type->code }} · {{ $type->name }} · {{ $type->is_active ? 'Aktif' : 'Nonaktif' }} <button type="button" wire:click="editMaster('type',{{ $type->id }})">Edit jenis dokumen</button></p>@endforeach</section>
<section class="scm-item"><h3>Persyaratan kategori</h3>@forelse($requirements as $requirement)<p>{{ $categories->firstWhere('id',$requirement->category_id)?->name }} · {{ $requirement->documentType->name }} · {{ $requirement->is_required ? 'Wajib' : 'Opsional' }} <button type="button" wire:click="editMaster('requirement',{{ $requirement->id }})">Edit persyaratan</button></p>@empty<p>Belum ada konfigurasi. Tambahkan persyaratan yang sesuai kebutuhan organisasi.</p>@endforelse</section></div>
</section>
<section class="scm-panel"><h2>Penandatangan PR/SR HO</h2>
    <p>Atur atasan, CBO, dan COO 3 per perusahaan dan departemen. Jika pilihan Pengajuan di atas Rp5 juta dicentang, CBO wajib Crosschecked by dan COO 3 mengisi Approved by. Jika tidak dicentang, Approved by cukup oleh CBO dan Crosschecked by opsional. Jika pengaju adalah atasan yang ditetapkan, Acknowledged by dilewati.</p>
    <form wire:submit="saveApprovalRoute"><fieldset wire:loading.attr="disabled" wire:target="saveApprovalRoute"><legend class="scm-sheet-sr">Pengaturan penandatangan HO</legend><div class="scm-grid">
        <x-scm.select model="approvalRoute.company" label="Perusahaan persetujuan" :options="['APCA'=>'APCA','KPMOG'=>'KPMOG']" required />
        <x-scm.select model="approvalRoute.department_id" label="Departemen pengaju" :options="$departments" required />
        <x-scm.select model="approvalRoute.acknowledger_id" label="Atasan / Acknowledged by" :options="$approvalUsers" required />
        <x-scm.select model="approvalRoute.cbo_id" label="CBO" :options="$approvalUsers" required />
        <x-scm.select model="approvalRoute.cbo3_id" label="COO 3 (untuk pengajuan yang ditandai di atas Rp5 juta)" :options="$approvalUsers" />
    </div><div class="scm-actions"><x-filament::button type="submit">Simpan penandatangan HO</x-filament::button><button type="button" wire:click="newApprovalRoute">Pengaturan baru</button></div></fieldset></form>
    <p role="status" wire:loading wire:target="saveApprovalRoute">Menyimpan penandatangan HO...</p>
    <p class="scm-help">COO 3 boleh belum diisi jika akunnya belum tersedia. Pengajuan yang ditandai di atas Rp5 juta ditahan sampai COO 3 ditetapkan.</p>
    @forelse($approvalRoutes as $route)<p>{{ $route->company }} · {{ $route->department->name }}: atasan {{ $route->acknowledger->name }}, CBO {{ $route->cbo->name }}, COO 3 {{ $route->cbo3?->name ?? 'belum ditetapkan' }}. <button type="button" wire:click="editApprovalRoute({{ $route->id }})">Edit penandatangan {{ $route->company }} {{ $route->department->name }}</button></p>
    @empty<p>Belum ada penandatangan HO. Draft dapat disimpan; pengajuan persetujuan memerlukan pengaturan ini.</p>@endforelse
</section>
