<x-filament-panels::page>
    @vite('resources/css/scm.css')
    <div class="scm-workspace">
        <nav class="scm-actions submission-report-types" aria-label="Jenis laporan">
            @foreach($types as $key => $label)
                <a href="{{ \App\Filament\Pages\SubmissionReport::getUrl(['type' => $key]) }}" @if($type === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <section class="scm-panel" aria-labelledby="submission-report-title">
            <h2 id="submission-report-title">Laporan {{ $types[$type] }}</h2>
            <form class="submission-report-filters" method="get" action="{{ \App\Filament\Pages\SubmissionReport::getUrl() }}">
                <input type="hidden" name="type" value="{{ $type }}">
                <div class="scm-grid">
                    <div class="scm-field"><label for="report-company">Perusahaan</label><select name="company" id="report-company">
                        @if(count($companies) > 1)<option value="">Semua perusahaan</option>@endif
                        @foreach($companies as $company)<option value="{{ $company }}" @selected(($filters['company'] ?? null) === $company)>{{ $company }}</option>@endforeach
                    </select></div>
                    <div class="scm-field"><label for="report-status">Status</label><select name="status" id="report-status">
                        <option value="">Semua status</option>
                        @foreach($statuses as $key => $label)<option value="{{ $key }}" @selected(($filters['status'] ?? null) === $key)>{{ $label }}</option>@endforeach
                    </select></div>
                    <div class="scm-field"><label for="report-from">Dari tanggal dokumen</label><input type="date" name="from" id="report-from" value="{{ $filters['from'] ?? '' }}"></div>
                    <div class="scm-field"><label for="report-until">Sampai tanggal dokumen</label><input type="date" name="until" id="report-until" value="{{ $filters['until'] ?? '' }}"></div>
                </div>
                <div class="scm-actions">
                    <x-filament::button type="submit">Terapkan filter</x-filament::button>
                    @if($filters || $filterErrors)<x-filament::button tag="a" color="gray" :href="\App\Filament\Pages\SubmissionReport::getUrl(['type' => $type])">Hapus filter</x-filament::button>@endif
                    @if($canExport && $records?->total())<x-filament::button tag="a" color="gray" download :href="route('reports.submissions.download', ['type' => $type, ...$filters])">Unduh CSV</x-filament::button>@endif
                </div>
            </form>
            @if($filterErrors)
                <ul class="scm-error" role="alert">@foreach($filterErrors as $message)<li>{{ $message }}</li>@endforeach</ul>
            @else
                <p role="status" wire:loading.delay>Memuat laporan…</p>
                @if($records->total())<p class="scm-help">{{ $records->total() }} dokumen</p>@endif
                <div class="scm-scroll" role="region" aria-label="Daftar {{ $types[$type] }}" tabindex="0">
                    <table class="submission-report-table">
                        <thead><tr>@foreach($columns as $column)<th scope="col">{{ $column }}</th>@endforeach</tr></thead>
                        <tbody>
                            @forelse($rows as $row)
                                <tr>
                                    <th scope="row"><a href="{{ $row['url'] }}">{{ $row['cells'][0] }}</a></th>
                                    @foreach(array_slice($row['cells'], 1, 7) as $value)<td>{{ $value ?? '–' }}</td>@endforeach
                                    <td>{{ $row['cells'][8] === null ? '–' : number_format($row['cells'][8] / 100, 2, ',', '.') }}</td>
                                    <td>{{ $row['cells'][9] ?? '–' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($columns) }}">Belum ada dokumen yang sesuai dengan filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $records->links() }}
            @endif
        </section>
    </div>
</x-filament-panels::page>
