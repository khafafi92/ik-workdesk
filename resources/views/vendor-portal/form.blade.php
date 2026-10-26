@php
    $readOnly = !in_array($registration->status, ['draft', 'correction']);
    $documents = collect(\App\Services\Scm\VendorForm::documents())->filter(fn ($definition) => $definition[1] === $step);
@endphp
<x-vendor-portal.layout :registration="$registration" :token="$token" :step="$step">
    <section class="vp-paper">
        <header class="vp-section-header"><p>Bagian {{ $step }} dari 5</p><h2>{{ \App\Services\Scm\VendorForm::TITLES[$step] }}</h2></header>
        @if($readOnly)<p>Kiriman terkunci selama pemeriksaan atau setelah diterima. Hubungi SCM jika data perlu diperbaiki.</p>
        @else<p>Isi data sesuai kondisi perusahaan. Tanda * wajib diisi. Halaman ini untuk perusahaan Indonesia.</p>@endif
        @if($step === 1)<p class="vp-note">Simpan bagian perusahaan terlebih dahulu. Daftar dokumen wajib di bawah mengikuti jawaban perubahan akta dan status PKP yang sudah disimpan.</p>@endif
        @if($step === 5)
            <div class="vp-declaration"><p>Saya menjamin informasi yang disampaikan benar dan lengkap sesuai pengetahuan saya. Saya memahami bahwa informasi tidak benar atau menyesatkan dapat menyebabkan perusahaan dikeluarkan dari daftar vendor yang disetujui.</p></div>
            <h3>Kelengkapan sebelum dikirim</h3>
            <p>Ringkasan mengikuti jawaban yang sudah disimpan dan dokumen yang tersedia. Bagian 5 diisi melalui pernyataan di bawah.</p>
            @foreach($checklist as $number => $completion)
                <section class="vp-document" aria-labelledby="completion-{{ $number }}">
                    <h4 id="completion-{{ $number }}"><a href="{{ route('vendor.portal', ['token' => $token, 'step' => $number]) }}">{{ $number }}. {{ $completion['title'] }}</a></h4>
                    @if(!$completion['answers'] && !$completion['documents'])<p>Isian dan lampiran lengkap.</p>
                    @else
                        <details><summary>Perlu dilengkapi: {{ count($completion['answers']) }} isian dan {{ count(array_unique(array_column($completion['documents'], 'code'))) }} dokumen</summary>
                            <ul class="vp-completion">
                                @foreach($completion['answers'] as $fieldName => $messages)
                                    @foreach($messages as $message)<li><a href="{{ route('vendor.portal', ['token' => $token, 'step' => $number]).($readOnly ? '' : '#answer-'.$fieldName) }}">{{ $message }}</a></li>@endforeach
                                @endforeach
                                @foreach($completion['documents'] as $issue)<li><a href="{{ route('vendor.portal', ['token' => $token, 'step' => $number]).'#document-'.$issue['code'] }}">{{ $issue['message'] }}</a></li>@endforeach
                            </ul>
                        </details>
                    @endif
                </section>
            @endforeach
            <p>SCM akan memeriksa seluruh jawaban dan dokumen. Mengirim form tidak langsung mengubah profil master vendor.</p>
        @endif
        <form method="post" action="{{ route('vendor.portal.save', ['token' => $token, 'step' => $step]) }}">
            @csrf<input type="hidden" name="version" value="{{ $registration->version }}">
            <div class="vp-fields">@foreach($fields as $key => $field)@include('vendor-portal.partials.field')@endforeach</div>
            @if(!$readOnly)<div class="vp-actions"><button type="submit" name="intent" value="{{ $step === 5 ? 'submit' : 'save' }}">{{ $step === 5 ? 'Kirim form dan dokumen ke SCM' : 'Simpan jawaban bagian ini' }}</button>@if($step < 5)<button type="submit" name="intent" value="continue" class="vp-secondary">Simpan dan lanjut ke bagian {{ $step + 1 }}</button>@endif</div>@endif
        </form>
    </section>
    @if($documents->isNotEmpty())
    <section class="vp-paper"><h2>{{ $step === 1 ? '1.4.3 Legalitas dan dokumen perusahaan' : 'Lampiran bagian '.$step }}</h2>
        <p>Simpan jawaban bagian ini sebelum mengunggah lampiran. PDF, JPG, atau PNG; maksimal {{ number_format(config('scm.upload_max_kb') / 1024, 0) }} MB per file. Gabungkan dokumen sejenis dalam satu PDF. Unggahan pengganti disimpan sebagai versi baru.</p>
        @if($step === 1)<p class="vp-note">Dokumen 7 dan 10 wajib untuk transaksi konstruksi di atas Rp100 juta dengan durasi kontrak lebih dari 3 bulan. Kondisi transaksi ditetapkan SCM pada undangan ini: {{ ($registration->context['is_construction'] ?? false) ? 'konstruksi, '.\App\Services\Scm\Decimal::money($registration->context['amount_minor']).', '.$registration->context['duration_months'].' bulan' : 'bukan transaksi konstruksi khusus' }}. Salinan PKP tidak wajib untuk Non PKP. Surat referensi bank asli diserahkan kepada SCM; unggah salinannya di sini.</p>@endif
        <div class="vp-documents">
        @foreach($documents as $code => $definition)
            @php
                $file = $files->get($code);
                $requiredDocument = \App\Services\Scm\VendorForm::required($code, $registration->answers ?? [], $registration->context);
            @endphp
            <article class="vp-document" id="document-{{ $code }}"><header><h3>{{ $definition[0] }}</h3><p>{{ $requiredDocument ? 'Wajib' : 'Bersyarat / opsional pada kiriman ini' }}</p></header>
                @if($file)<p><a href="{{ route('vendor.portal.download', ['token' => $token, 'file' => $file->id]) }}">Unduh {{ $file->original_name }}</a> · Nomor: {{ $file->number }}@if($file->expires_at) · Berlaku sampai {{ $file->expires_at->format('d/m/Y') }}@endif</p>@else<p>Belum diunggah.</p>@endif
                @if(!$readOnly)<details><summary>{{ $file ? 'Unggah versi pengganti' : 'Unggah dokumen' }}</summary>
                    <form method="post" enctype="multipart/form-data" action="{{ route('vendor.portal.upload', ['token' => $token, 'code' => $code]) }}">
                        @csrf<input type="hidden" name="version" value="{{ $registration->version }}">
                        <div class="vp-fields">
                            <div class="vp-field"><label for="number-{{ $code }}">Nomor dokumen (jika tercantum)</label><input id="number-{{ $code }}" name="number" maxlength="255"></div>
                            <div class="vp-field"><label for="issued-{{ $code }}">Tanggal terbit (jika tercantum)</label><input id="issued-{{ $code }}" name="issued_at" type="date"></div>
                            @if($definition[3])<div class="vp-field"><label for="expiry-{{ $code }}">Berlaku sampai *</label><input id="expiry-{{ $code }}" name="expires_at" type="date" required></div>@endif
                            <div class="vp-field vp-wide"><label for="file-{{ $code }}">File {{ $definition[0] }} *</label><input id="file-{{ $code }}" name="file" type="file" accept=".pdf,.jpg,.jpeg,.png" required></div>
                        </div><button type="submit">{{ $file ? 'Simpan versi pengganti' : 'Simpan dokumen' }}</button>
                    </form>
                </details>@endif
            </article>
        @endforeach
        </div>
    </section>
    @endif
</x-vendor-portal.layout>
