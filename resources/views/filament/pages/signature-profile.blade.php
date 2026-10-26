<x-filament-panels::page>
    <div class="scm-workspace">
        @if($errors->any())<div class="scm-error" role="alert"><h2>Periksa gambar tanda tangan</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <section class="scm-panel">
            <h2>TTD {{ auth()->user()->name }}</h2>
            <p>Simpan gambar tanda tangan Anda untuk PR/SR. Setiap dokumen tetap perlu Anda tandatangani pada tahap yang menjadi tugas Anda dengan menekan tombol Tanda tangani.</p>
            @if($signature)
                <img class="scm-profile-signature" src="{{ route('scm.signature-profile', $signature) }}" alt="Tanda tangan profil Anda" width="240" height="100">
                <p class="scm-help">Mengganti gambar di profil tidak mengubah TTD pada dokumen yang sudah ditandatangani.</p>
            @else
                <p>Belum ada gambar tanda tangan di profil.</p>
            @endif
            <form wire:submit="saveSignature">
                <fieldset wire:loading.attr="disabled" wire:target="signatureFile,saveSignature">
                    <legend class="scm-sheet-sr">Unggah tanda tangan profil</legend>
                    <x-scm.field model="signatureFile" label="Gambar TTD PNG/JPG" type="file" accept="image/png,image/jpeg" required help="Maksimal 2 MB dan 4096 × 4096 piksel. Gunakan gambar dengan latar putih atau transparan." />
                    <div class="scm-actions"><x-filament::button type="submit">{{ $signature ? 'Ganti TTD profil' : 'Simpan TTD profil' }}</x-filament::button></div>
                </fieldset>
                <p role="status" wire:loading wire:target="signatureFile,saveSignature">Mengunggah tanda tangan. Mohon tunggu...</p>
            </form>
        </section>
    </div>
</x-filament-panels::page>
