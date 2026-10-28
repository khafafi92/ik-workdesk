<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Portal Vendor · IK WorkDesk</title>@vite('resources/css/vendor-portal.css')</head>
<body class="vendor-portal">
<a class="vp-skip" href="#main">Langsung ke portal vendor</a>
<div class="vp-shell">
    <header class="vp-header"><div><h1>Portal Vendor</h1><p>Isi data perusahaan dan berkas Form 005. Gunakan Vendor ID untuk membuka kembali data dan mengajukan pembaruan.</p></div></header>
    <main id="main" tabindex="-1">
        @if($errors->any())<section class="vp-error" role="alert"><h2>Periksa isian</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>@endif
        <section class="vp-paper"><h2>Vendor baru</h2><p>Mulai pendaftaran tanpa akun internal. Vendor ID diberikan setelah langkah ini; simpan ID tersebut untuk membuka kembali data Anda.</p>
            <form method="post" action="{{ route('vendor.register') }}">@csrf
                <div class="vp-field"><label for="vendor-company">Perusahaan tujuan</label><select id="vendor-company" name="company" required>@foreach(config('scm.companies') as $key => $company)<option value="{{ $key }}" @selected(old('company', 'KPMOG') === $key)>{{ $company['name'] }}</option>@endforeach</select></div>
                <div class="vp-field"><label for="vendor-name">Nama perusahaan vendor</label><input id="vendor-name" name="name" value="{{ old('name') }}" maxlength="255" required autocomplete="organization"></div>
                <div class="vp-field"><label for="vendor-type">Jenis penyedia</label><select id="vendor-type" name="provider_type" required>@foreach(['barang'=>'Barang', 'jasa'=>'Jasa', 'keduanya'=>'Barang dan jasa'] as $value => $label)<option value="{{ $value }}" @selected(old('provider_type') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="vp-actions"><button type="submit">Mulai pendaftaran vendor</button></div>
            </form>
        </section>
        <section class="vp-paper"><h2>Lanjutkan atau perbarui data vendor</h2><p>Masukkan Vendor ID untuk menampilkan data perusahaan beserta berkas Anda. Setelah data diterima SCM, pembaruan disiapkan dari data dan berkas sebelumnya.</p>
            <form method="post" action="{{ route('vendor.resume') }}">@csrf
                <div class="vp-field"><label for="vendor-code">Vendor ID</label><input id="vendor-code" name="access_code" type="text" maxlength="100" required autocomplete="off" autocapitalize="characters" spellcheck="false" aria-describedby="vendor-code-help"><p id="vendor-code-help">Masukkan ID berawalan VND yang diberikan saat pendaftaran.</p></div>
                <div class="vp-actions"><button type="submit">Buka data vendor</button></div>
            </form>
        </section>
    </main>
    <footer class="vp-footer"><p>SCM memeriksa setiap kiriman sebelum menerima profil dan dokumen ke master.</p><a href="{{ route('login') }}">Masuk untuk petugas internal</a></footer>
</div>
</body></html>
