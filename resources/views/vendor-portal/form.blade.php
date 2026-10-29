@php
    $readOnly = !in_array($registration->status, ['draft', 'correction']);
    $savedAnswers = $registration->answers ?? [];
    $savedOrigin = \App\Services\Scm\VendorForm::origin($savedAnswers);
    $savedBasis = \App\Services\Scm\VendorForm::basis($savedAnswers);
    $documentGroups = collect(\App\Services\Scm\VendorForm::documentGroups($savedAnswers, $registration->context))
        ->map(fn ($codes) => array_values(array_filter($codes, fn ($code) => \App\Services\Scm\VendorForm::documents()[$code][1] === $step)))->filter();
@endphp
<x-vendor-portal.layout :registration="$registration" :token="$token" :step="$step">
    <section class="vp-paper">
        <header class="vp-section-header"><p>Bagian {{ $step }} dari 5</p><h2>{{ \App\Services\Scm\VendorForm::TITLES[$step] }}</h2></header>
        @if($readOnly)<p>Kiriman terkunci selama pemeriksaan atau setelah diterima. Hubungi SCM jika data perlu diperbaiki.</p>
        @else<p>Isi sesuai kondisi vendor. Tanda * wajib diisi. Lampiran mengikuti asal perusahaan dan jawaban yang disimpan.</p>@endif
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
                    @if(!empty($completion['warnings']))<ul class="vp-completion vp-expired">@foreach($completion['warnings'] as $warning)<li><a href="{{ route('vendor.portal', ['token' => $token, 'step' => $number]).($warning['field'] ? '#answer-'.$warning['field'] : '#document-'.$warning['code']) }}">{{ $warning['message'] }}</a></li>@endforeach</ul>@endif
                </section>
            @endforeach
            <p>SCM akan memeriksa seluruh jawaban dan dokumen. Mengirim form tidak langsung mengubah profil master vendor.</p>
        @endif
        <form method="post" action="{{ route('vendor.portal.save', ['token' => $token, 'step' => $step]) }}" @if(!$readOnly)data-vp-form @endif data-vp-address-url="{{ url('/vendor/wilayah') }}" data-saved-origin="{{ $savedOrigin }}" data-saved-basis="{{ $savedBasis }}" data-saved-answers="{{ json_encode($registration->answers[$step] ?? []) }}">
            @csrf<input type="hidden" name="version" value="{{ $registration->version }}">
            @if($step === 1)
                <fieldset class="vp-field-group vp-vendor-kind"><legend>Jenis vendor dan dokumen</legend>
                    @if($readOnly)<p><strong>{{ $savedOrigin === 'foreign' ? 'Perusahaan luar Indonesia' : 'Vendor Indonesia' }}</strong> · {{ $savedBasis === 'npwp_ktp' ? 'Pengganti NPWP dan KTP' : 'Dokumen perusahaan' }}</p>
                    @else
                    <div class="vp-fields">
                        <div class="vp-field"><label for="answer-company_origin">Asal perusahaan *</label><select id="answer-company_origin" name="answers[company_origin]" required><option value="indonesia" @selected(old('answers.company_origin', $savedOrigin) === 'indonesia')>Indonesia</option><option value="foreign" @selected(old('answers.company_origin', $savedOrigin) === 'foreign')>Luar Indonesia</option></select></div>
                        <div class="vp-field" data-vp-basis><label for="answer-document_basis">Dokumen vendor Indonesia *</label><select id="answer-document_basis" name="answers[document_basis]" required><option value="company" @selected(old('answers.document_basis', $savedBasis) === 'company')>Dokumen perusahaan</option><option value="npwp_ktp" @selected(old('answers.document_basis', $savedBasis) === 'npwp_ktp')>Tidak memiliki dokumen perusahaan: NPWP dan KTP</option></select></div>
                    </div>
                    <p class="vp-note">Vendor Indonesia tanpa dokumen perusahaan cukup melampirkan NPWP dan KTP. Vendor luar Indonesia memakai dokumen perusahaan asing.</p>
                    @endif
                </fieldset>
            @endif
            @foreach(\App\Services\Scm\VendorForm::fieldGroups($step, $savedAnswers) as $groupTitle => $keys)
                <fieldset class="vp-field-group"><legend>{{ $groupTitle }}</legend><div class="vp-fields">
                    @foreach($keys as $key)@if(isset($fields[$key]))
                        @continue($readOnly && in_array($key, ['has_amendments', 'non_pkp']) && ($savedOrigin === 'foreign' || $savedBasis === 'npwp_ktp'))
                        @php($field = $fields[$key])@include('vendor-portal.partials.field')
                    @endif @endforeach
                </div></fieldset>
            @endforeach
            @if(!$readOnly)<div class="vp-actions"><button type="submit" name="intent" value="{{ $step === 5 ? 'submit' : 'save' }}">{{ $step === 5 ? 'Kirim form dan dokumen ke SCM' : 'Simpan jawaban bagian ini' }}</button>@if($step < 5)<button type="submit" name="intent" value="continue" class="vp-secondary">Simpan dan lanjut ke bagian {{ $step + 1 }}</button>@endif</div>@endif
        </form>
    </section>
    @if($documentGroups->isNotEmpty())
    <section class="vp-paper" data-vp-documents><h2>{{ $step === 1 ? 'Dokumen vendor' : 'Lampiran bagian '.$step }}</h2>
        <p class="vp-note">{{ $savedOrigin === 'foreign' ? 'Perusahaan luar Indonesia' : 'Vendor Indonesia' }} · {{ $savedBasis === 'npwp_ktp' ? 'Pengganti NPWP dan KTP' : 'Dokumen perusahaan' }}. Pilih dokumen untuk membuka unggahan. PDF/JPG/PNG, maksimal {{ number_format(config('scm.upload_max_kb') / 1024, 0) }} MB per file.</p>
        <p class="vp-feedback" data-vp-save-first hidden role="status">Simpan jawaban bagian ini untuk memperbarui daftar dokumen sebelum mengunggah.</p>
        @if($step === 1 && $savedOrigin === 'indonesia' && $savedBasis !== 'npwp_ktp')<p class="vp-note">Dokumen wajib: Akta Pendirian, Perizinan, NPWP, dan KTP. Lampiran lainnya opsional. Perubahan akta dan PKP tersedia sesuai jawaban yang disimpan; pernyataan hukum dan referensi bank tersedia untuk konteks konstruksi yang sesuai.</p>@endif
        <div data-vp-document-list>
            @foreach($documentGroups as $groupTitle => $codes)
                <section class="vp-document-group"><header><h3>{{ $groupTitle }}</h3><p>{{ $files->filter(fn ($file) => in_array($file->code, $codes, true))->count() }} dari {{ count($codes) }} diunggah</p></header><div class="vp-documents">
                    @foreach($codes as $code)@include('vendor-portal.partials.document', ['definition' => \App\Services\Scm\VendorForm::documents()[$code], 'file' => $files->get($code)])@endforeach
                </div></section>
            @endforeach
        </div>
    </section>
    @elseif(in_array($step, [2, 3, 4]))<p class="vp-note">Tidak ada lampiran tambahan yang diminta untuk jenis vendor dan jawaban yang sudah disimpan pada bagian ini.</p>
    @endif
</x-vendor-portal.layout>
