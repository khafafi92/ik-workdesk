@php
    $kinds = \App\Services\Scm\ApcaVendorChecklist::KINDS;
    $scales = \App\Services\Scm\ApcaVendorChecklist::SCALES;
    $company = $registration->answers[1] ?? [];
@endphp
<fieldset class="vp-field-group">
    <legend>Jenis dan skala penyedia</legend>
    @if($readOnly)
        <p>{{ $kinds[$company['apca_kind'] ?? ''] ?? 'Belum diisi' }} - {{ $scales[$company['apca_scale'] ?? ''] ?? 'Belum diisi' }}</p>
    @else
        <input type="hidden" name="answers[company_origin]" value="indonesia">
        <input type="hidden" name="answers[document_basis]" value="company">
        <div class="vp-fields">
            <div class="vp-field">
                <label for="answer-apca_kind">Jenis penyedia *</label>
                <select id="answer-apca_kind" name="answers[apca_kind]" required>
                    <option value="">Pilih jenis penyedia</option>
                    @foreach($kinds as $value => $label)
                        <option value="{{ $value }}" @selected(old('answers.apca_kind', $company['apca_kind'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="vp-field">
                <label for="answer-apca_scale">Skala usaha *</label>
                <select id="answer-apca_scale" name="answers[apca_scale]" required aria-describedby="apca-scale-help">
                    <option value="">Pilih skala usaha</option>
                    @foreach($scales as $value => $label)
                        <option value="{{ $value }}" @selected(old('answers.apca_scale', $company['apca_scale'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <p id="apca-scale-help">Skala Kecil mencakup usaha mikro/kecil. Skala Besar mencakup usaha menengah/besar. Untuk jasa konstruksi, pilih menurut kualifikasi SBU yang sesuai.</p>
            </div>
            @foreach ([
                'is_producer' => ['barang', 'Apakah perusahaan memproduksi sendiri barang yang ditawarkan?'],
                'imports_goods' => ['barang', 'Apakah barang yang ditawarkan mencakup barang impor?'],
                'construction_consultancy' => ['konsultan', 'Apakah jasa yang ditawarkan mencakup konsultansi konstruksi?'],
                'high_risk_work' => ['kontraktor', 'Apakah pekerjaan mencakup kegiatan lapangan berisiko?'],
            ] as $key => [$kind, $label])
                <div class="vp-field" data-vp-condition="apca_kind" data-vp-value="{{ $kind }}">
                    <label for="answer-{{ $key }}">{{ $label }}</label>
                    <select id="answer-{{ $key }}" name="answers[{{ $key }}]">
                        <option value="">Pilih jawaban</option>
                        @foreach (['yes' => 'Ya', 'no' => 'Tidak'] as $value => $text)
                            <option value="{{ $value }}" @selected(old('answers.'.$key, $company[$key] ?? '') === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>
    @endif
</fieldset>
