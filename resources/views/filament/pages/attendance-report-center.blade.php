<x-filament-panels::page>
    @php
        $imports = $this->getImports();
        $selectedImport = $this->selectedImport;
    @endphp

    <div class="att-page att-report-center">
        <section class="att-section" aria-labelledby="attendance-history-title">
            <div class="att-section-header">
                <h2 id="attendance-history-title" class="att-section-title">Riwayat laporan</h2>
                <p class="att-section-desc">Pilih laporan yang sudah diupload untuk melihat status dan hasilnya.</p>
            </div>
            @if ($imports->isNotEmpty())
                <label for="attendance-period" class="att-label">Periode laporan</label>
                <select id="attendance-period" wire:model.live="attendanceImportId" class="att-select">
                    @foreach ($imports as $import)
                        <option value="{{ $import->id }}">{{ $import->period_name }} ({{ $this->statusLabel($import->status) }})</option>
                    @endforeach
                </select>
                <p wire:loading wire:target="attendanceImportId" role="status" class="att-section-desc">Memuat laporan...</p>
            @else
                <p>Belum ada laporan attendance.</p>
                @if (\App\Filament\Resources\AttendanceImports\AttendanceImportResource::canCreate())
                    <p class="att-section-desc">Mulai dari tombol Upload data baru, lalu pilih file lokasi absen dan total jam kerja.</p>
                @endif
            @endif
        </section>

        @if ($selectedImport)
            <section class="att-section" aria-labelledby="attendance-result-title" wire:key="attendance-{{ $selectedImport->id }}">
                <div class="att-section-header">
                    <h2 id="attendance-result-title" class="att-section-title">{{ $selectedImport->period_name }}</h2>
                    <p class="att-section-desc" role="status">Status: {{ $this->statusLabel($selectedImport->status) }}</p>
                </div>
                @if ($selectedImport->status === 'processed')
                    <p>Laporan siap. Buka hasil untuk memeriksa lokasi absen, durasi, pulang pukul 19.00 atau setelahnya, dan total jam kerja.</p>
                    <div class="att-report-actions">
                        <x-filament::button tag="a" :href="\App\Filament\Resources\AttendanceImports\AttendanceImportResource::getUrl('results', ['record' => $selectedImport])">Lihat hasil laporan</x-filament::button>
                        @if (auth()->user()?->hasPermission('attendance.view'))
                            <x-filament::button tag="a" color="gray" :href="url('/attendance-imports/'.$selectedImport->id.'/download')">Unduh Excel</x-filament::button>
                        @endif
                    </div>
                    <p class="att-section-desc">Diproses {{ $selectedImport->processed_at?->format('d M Y H:i') ?? '-' }}</p>
                @elseif ($selectedImport->status === 'processing')
                    <p role="status" wire:poll.5s>File sedang diproses. Hasil akan tersedia setelah proses selesai.</p>
                @else
                    @if ($selectedImport->status === 'failed')
                        <p role="alert">Proses belum berhasil. {{ $selectedImport->notes }}</p>
                    @else
                        <p>File sudah tersimpan dan belum diproses.</p>
                    @endif
                    <div class="att-report-actions">
                        {{ $this->processAction }}
                        @if (\App\Filament\Resources\AttendanceImports\AttendanceImportResource::canEdit($selectedImport))
                            <x-filament::button tag="a" color="gray" :href="\App\Filament\Resources\AttendanceImports\AttendanceImportResource::getUrl('edit', ['record' => $selectedImport])">Perbaiki file</x-filament::button>
                        @endif
                    </div>
                    @unless (auth()->user()?->hasPermission('attendance.manage'))
                        <p class="att-section-desc">Menunggu pengguna dengan izin proses attendance.</p>
                    @endunless
                @endif
                <details class="att-report-files">
                    <summary>File sumber</summary>
                    <p><strong>Lokasi absen:</strong> {{ $selectedImport->attendance_file_name ?? '-' }}</p>
                    <p><strong>Total jam kerja:</strong> {{ $selectedImport->work_hour_file_name ?? '-' }}</p>
                </details>
            </section>
        @elseif ($imports->isNotEmpty())
            <p role="status">Laporan tidak ditemukan. Pilih periode lain.</p>
        @endif
    </div>
</x-filament-panels::page>
