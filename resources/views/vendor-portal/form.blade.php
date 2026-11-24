@php
    $readOnly = !in_array($registration->status, ['draft', 'correction']);
    $savedAnswers = $registration->answers ?? [];
    $savedOrigin = \App\Services\Scm\VendorForm::origin($savedAnswers);
    $savedBasis = \App\Services\Scm\VendorForm::basis($savedAnswers);
    $isApca = \App\Services\Scm\ApcaVendorChecklist::applies($registration->context);
    $titles = \App\Services\Scm\VendorForm::titles($registration->context);
    $stepNumber = array_search($step, array_keys($titles), true) + 1;
    $definitions = \App\Services\Scm\VendorForm::documents($registration->context, $savedAnswers);
    $documentGroups = collect(\App\Services\Scm\VendorForm::documentGroups($savedAnswers, $registration->context))
        ->map(fn ($codes) => array_values(array_filter($codes, fn ($code) => $definitions[$code][1] === $step)))->filter();
    $showApcaChecklist = $isApca && $step === 2;
    $checklistGroups = $showApcaChecklist ? \App\Services\Scm\ApcaVendorChecklist::numberedGroups($savedAnswers) : $documentGroups;
    $profileSaved = $showApcaChecklist && $documentGroups->isNotEmpty();
@endphp
<x-vendor-portal.layout :registration="$registration" :token="$token" :step="$step" :vendor-id="$vendorId">
    <section class="vp-paper">
        <header class="vp-section-header"><p>Bagian {{ $stepNumber }} dari {{ count($titles) }}</p><h2>{{ $titles[$step] }}</h2></header>
        @if($readOnly)
            @if($registration->status === 'submitted')<p>Formulir sudah terkirim dan menunggu pemeriksaan SCM. Isian terkunci selama pemeriksaan. SCM akan menerima profil atau meminta perbaikan.</p>
            @else<p>Profil sudah diterima SCM. @if($registration->portal_access_id)Untuk memperbarui data, kembali ke Portal Vendor dan masukkan Vendor ID untuk membuat draft pembaruan.@else Hubungi SCM untuk meminta undangan pembaruan formulir.@endif</p>@endif
        @elseif($isApca)
            @if($step === 1)
                <p>Simpan jenis penyedia dan skala usaha untuk mengetahui status dokumen pada checklist.</p>
                <a href="{{ route('vendor.portal', ['token' => $token, 'step' => 2]) }}">Lihat checklist 28 poin</a>
            @elseif($step === 2)
                @if($profileSaved)
                    <p>Unggah dokumen sesuai checklist. Jika satu poin meminta beberapa berkas, gabungkan menjadi satu PDF.</p>
                @else
                    <p>Simpan profil usaha di <a href="{{ route('vendor.portal', ['token' => $token, 'step' => 1]) }}">Data Perusahaan</a> untuk mengetahui status tiap dokumen dan membuka unggahan.</p>
                @endif
            @endif
        @else
            <p>Isi sesuai kondisi vendor. Tanda * wajib diisi. Lampiran mengikuti asal perusahaan dan jawaban yang disimpan.</p>
            <p class="vp-note">Simpan jawaban belum mengirim formulir. Lengkapi bagian 1 sampai 4 dan dokumen wajib, lalu isi serta konfirmasikan Bagian 5: Pernyataan dan klik Kirim form dan dokumen ke SCM. Status akan menjadi Terkirim / menunggu pemeriksaan.</p>
            @if($step < 5)<p>Simpan perubahan bagian ini sebelum berpindah.</p><div class="vp-actions"><a href="{{ route('vendor.portal', ['token' => $token, 'step' => 5]) }}">Periksa kelengkapan dan kirim (Bagian 5)</a></div>@endif
        @endif
        @if($step === 5)
            <div class="vp-declaration"><p>Saya menjamin informasi yang disampaikan benar dan lengkap sesuai pengetahuan saya. Saya memahami bahwa informasi tidak benar atau menyesatkan dapat menyebabkan perusahaan dikeluarkan dari daftar vendor yang disetujui.</p></div>
            <h3>Kelengkapan sebelum dikirim</h3>
            <p>Ringkasan mengikuti jawaban yang sudah disimpan dan dokumen yang tersedia. Bagian 5 diisi melalui pernyataan di bawah.</p>
            @foreach($checklist as $number => $completion)
                <section class="vp-document" aria-labelledby="completion-{{ $number }}">
                    <h4 id="completion-{{ $number }}"><a href="{{ route('vendor.portal', ['token' => $token, 'step' => $number]) }}">{{ $loop->iteration }}. {{ $completion['title'] }}</a></h4>
                    @if($isApca && $number === 2 && ! \App\Services\Scm\ApcaVendorChecklist::requirements($savedAnswers))<p>Lengkapi profil usaha pada Data Perusahaan untuk memeriksa dokumen.</p>
                    @elseif(!$completion['answers'] && !$completion['documents'])<p>Isian dan lampiran lengkap.</p>
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
        @unless($isApca && $step === 2)
        <form method="post" action="{{ route('vendor.portal.save', ['token' => $token, 'step' => $step]) }}" @if(!$readOnly)data-vp-form @endif data-vp-address-url="{{ url('/vendor/wilayah') }}" data-saved-origin="{{ $savedOrigin }}" data-saved-basis="{{ $savedBasis }}" data-saved-answers="{{ json_encode($registration->answers[$step] ?? []) }}">
            @csrf<input type="hidden" name="version" value="{{ $registration->version }}">
            @if($step === 1 && $isApca)
                @include('vendor-portal.partials.apca-profile')
            @elseif($step === 1)
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
        @endunless
    </section>
    @if($showApcaChecklist || $documentGroups->isNotEmpty())
    <section class="vp-paper" data-vp-documents><h2>{{ $showApcaChecklist ? 'Daftar dokumen 1–28' : ($step === 1 ? 'Dokumen vendor' : 'Lampiran bagian '.$step) }}</h2>
        @if($profileSaved)
            <p>Profil yang digunakan <strong>{{ \App\Services\Scm\ApcaVendorChecklist::KINDS[$savedAnswers[1]['apca_kind']] }} - {{ \App\Services\Scm\ApcaVendorChecklist::SCALES[$savedAnswers[1]['apca_scale']] }}</strong></p>
            <p class="vp-note">Pilih dokumen untuk melihat ketentuan dan tempat unggah. Anda bisa mengirim pendaftaran tanpa mengunggah dokumen opsional. PDF/JPG/PNG, maksimal {{ number_format(config('scm.upload_max_kb') / 1024, 0) }} MB per file.</p>
        @elseif(!$showApcaChecklist)
            <p class="vp-note">{{ $savedOrigin === 'foreign' ? 'Perusahaan luar Indonesia' : 'Vendor Indonesia' }} · {{ $savedBasis === 'npwp_ktp' ? 'Pengganti NPWP dan KTP' : 'Dokumen perusahaan' }}. Pilih dokumen untuk membuka unggahan. PDF/JPG/PNG, maksimal {{ number_format(config('scm.upload_max_kb') / 1024, 0) }} MB per file.</p>
        @endif
        <p class="vp-feedback" data-vp-save-first hidden role="status">Simpan jawaban bagian ini untuk memperbarui daftar dokumen sebelum mengunggah.</p>
        @if($step === 1 && $savedOrigin === 'indonesia' && $savedBasis !== 'npwp_ktp')<p class="vp-note">Dokumen wajib: Akta Pendirian, Perizinan, NPWP, dan KTP. Lampiran lainnya opsional. Perubahan akta dan PKP tersedia sesuai jawaban yang disimpan; pernyataan hukum dan referensi bank tersedia untuk konteks konstruksi yang sesuai.</p>@endif
        <div data-vp-document-list>
            @foreach($checklistGroups as $groupTitle => $codes)
                @php($applicableCodes = $documentGroups->get($groupTitle, []))
                <section class="vp-document-group">
                    <header>
                        <h3>{{ $groupTitle }}</h3>
                        @if($applicableCodes)
                            <p>{{ $files->filter(fn ($file) => in_array($file->code, $applicableCodes, true))->count() }} dari {{ count($applicableCodes) }} {{ $showApcaChecklist ? 'dokumen yang berlaku ' : '' }}diunggah</p>
                        @endif
                    </header>
                    <div class="vp-documents">
                        @foreach($codes as $position => $code)
                            @php($applicable = in_array($code, $applicableCodes, true))
                            @include('vendor-portal.partials.document', [
                                'definition' => $definitions[$code],
                                'file' => $applicable ? $files->get($code) : null,
                                'point' => $showApcaChecklist ? $position : null,
                                'unavailableStatus' => $applicable ? null : ($profileSaved ? 'Tidak berlaku' : 'Menunggu profil usaha'),
                            ])
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
        @if($isApca)<div class="vp-actions"><a href="{{ route('vendor.portal', ['token' => $token, 'step' => 5]) }}">Lanjut ke Pernyataan</a></div>@endif
    </section>
    @elseif(!$isApca && in_array($step, [2, 3, 4]))<p class="vp-note">Tidak ada lampiran tambahan yang diminta untuk jenis vendor dan jawaban yang sudah disimpan pada bagian ini.</p>
    @endif
</x-vendor-portal.layout>
