<x-filament-panels::page>
    @php
        $imports = $this->getImports();
        $selectedImport = $this->selectedImport;
    @endphp

    <div class="att-page">
        <div class="att-section">
            <div class="att-section-header">
                <div class="att-section-title">Pilih Periode Attendance</div>
                <div class="att-section-desc">
                    Setiap upload Excel mewakili 1 periode cut off, misalnya 21 Mei - 20 Juni 2026.
                </div>
            </div>

            <div class="att-filter-grid">
                <div>
                    <label class="att-label">Periode / Bulan</label>

                    <select wire:model.live="attendanceImportId" class="att-select">
                        @forelse ($imports as $import)
                            <option value="{{ $import->id }}">
                                {{ $import->period_name }}
                                —
                                {{ $import->attendance_file_name }}
                                /
                                {{ $import->work_hour_file_name }}
                            </option>
                        @empty
                            <option value="">Belum ada upload</option>
                        @endforelse
                    </select>
                </div>

                <div>
                    <label class="att-label">Status</label>
                    <div class="att-input" style="display: flex; align-items: center;">
                        {{ $selectedImport?->status ?? '-' }}
                    </div>
                </div>

                <div>
                    <label class="att-label">Processed At</label>
                    <div class="att-input" style="display: flex; align-items: center;">
                        {{ $selectedImport?->processed_at?->format('d M Y H:i') ?? '-' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="att-stats">
            <a href="{{ $this->getActivityUrl() }}" class="att-stat-card" style="text-decoration: none;">
                <div class="att-stat-label">Open</div>
                <div class="att-stat-value">Activity Check</div>
                <div class="att-section-desc">
                    Cek lokasi, durasi, dan pulang 19:00.
                </div>
            </a>

            <a href="{{ $this->getWorkHourUrl() }}" class="att-stat-card" style="text-decoration: none;">
                <div class="att-stat-label">Open</div>
                <div class="att-stat-value">Total Jam Kerja</div>
                <div class="att-section-desc">
                    Summary total jam kerja periode.
                </div>
            </a>

            <a href="{{ $this->getUploadUrl() }}" class="att-stat-card" style="text-decoration: none;">
                <div class="att-stat-label">Upload</div>
                <div class="att-stat-value">New Period</div>
                <div class="att-section-desc">
                    Upload activity.xlsx dan all time.xlsx.
                </div>
            </a>
        </div>

        @if ($selectedImport)
            <div class="att-section">
                <div class="att-section-header">
                    <div class="att-section-title">Selected Period</div>
                    <div class="att-section-desc">
                        Detail upload yang sedang dipilih.
                    </div>
                </div>

                <div class="att-table-wrap">
                    <table class="att-table att-summary-table">
                        <tbody>
                            <tr>
                                <th>Period Name</th>
                                <td>{{ $selectedImport->period_name }}</td>
                            </tr>
                            <tr>
                                <th>Activity File</th>
                                <td>{{ $selectedImport->attendance_file_name }}</td>
                            </tr>
                            <tr>
                                <th>All Time File</th>
                                <td>{{ $selectedImport->work_hour_file_name }}</td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>{{ $selectedImport->status }}</td>
                            </tr>
                            <tr>
                                <th>Notes</th>
                                <td>{{ $selectedImport->notes ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
