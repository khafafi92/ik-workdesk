@if($files->isNotEmpty())
    <link rel="stylesheet" href="{{ asset('css/document-print.css') }}?v={{ filemtime(public_path('css/document-print.css')) }}">
    @vite('resources/js/document-print.js')
    <section class="document-attachments" aria-label="Lampiran {{ $documentNumber }}" data-print-attachments data-document-number="{{ $documentNumber }}">
        <h2 class="document-attachments-title">Lampiran {{ $documentNumber }}</h2>
        <p class="document-attachments-progress" role="status" data-attachment-progress>Menyiapkan lampiran untuk dicetak…</p>
        <noscript><p>Aktifkan JavaScript untuk mencetak isi lampiran. File asli tersedia pada tautan di bawah.</p></noscript>
        @foreach($files->sortBy('id') as $file)
            <div class="document-attachment" data-print-attachment data-mime="{{ $file->mime }}">
                <p class="document-attachment-link"><a href="{{ route($downloadRoute, $file) }}">{{ $file->original_name }}</a></p>
                <p class="document-attachment-error" data-attachment-error hidden>Pratinjau lampiran gagal. Buka file asli untuk mencetaknya.</p>
                <div data-attachment-pages></div>
            </div>
        @endforeach
    </section>
@endif
