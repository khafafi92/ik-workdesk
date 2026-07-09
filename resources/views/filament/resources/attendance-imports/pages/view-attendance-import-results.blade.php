<x-filament-panels::page>
    @php
        $stats = $this->getStats();
        $results = $this->getResults();
        $workHourSummaries = $this->getWorkHourSummaries();
        $import = $this->getImport();
    @endphp

    <div class="att-page">

        <div class="att-section">
            <div class="att-section-header"
                style="display: flex; justify-content: space-between; align-items: center; gap: 12px;">
                <div>
                    <div class="att-section-title">Download Report</div>
                    <div class="att-section-desc">
                        Download hasil Activity Check dan Work Hour Summary dalam 1 file Excel.
                    </div>
                </div>

                <a href="{{ route('attendance-imports.download', $import) }}" class="att-download-btn">
                    Download Excel
                </a>
            </div>
        </div>

        <div class="att-stats">
            <div class="att-stat-card">
                <div class="att-stat-label">Activity Rows</div>
                <div class="att-stat-value">{{ number_format($stats['results']) }}</div>
            </div>

            <div class="att-stat-card">
                <div class="att-stat-label">Lokasi Sesuai</div>
                <div class="att-stat-value att-stat-success">{{ number_format($stats['location_ok']) }}</div>
            </div>

            <div class="att-stat-card">
                <div class="att-stat-label">Tidak Sesuai</div>
                <div class="att-stat-value att-stat-danger">{{ number_format($stats['location_not_ok']) }}</div>
            </div>

            <div class="att-stat-card">
                <div class="att-stat-label">Pulang 19:00 UP</div>
                <div class="att-stat-value att-stat-warning">{{ number_format($stats['checkout_late']) }}</div>
            </div>

            <div class="att-stat-card">
                <div class="att-stat-label">Work Hour Summary</div>
                <div class="att-stat-value">{{ number_format($stats['work_hour_summaries']) }}</div>
            </div>
        </div>

        <div class="att-filter-card">
            <div class="att-filter-grid">
                <div>
                    <label class="att-label">Search</label>

                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="text" wire:model="search" wire:keydown.enter="applyFilters"
                            placeholder="Cari employee, lokasi, alamat..." class="att-input">

                        <button type="button" wire:click="applyFilters" wire:loading.attr="disabled"
                            class="att-download-btn">
                            Search
                        </button>

                        <button type="button" wire:click="clearFilters" wire:loading.attr="disabled"
                            class="att-download-btn">
                            Reset
                        </button>
                    </div>
                </div>

                <div>
                    <label class="att-label">Cek Lokasi</label>
                    <select wire:model.live="locationCheck" class="att-select">
                        <option value="">All</option>
                        <option value="Sesuai">Sesuai</option>
                        <option value="Tidak Sesuai">Tidak Sesuai</option>
                    </select>
                </div>

                <div>
                    <label class="att-label">Cek Pulang</label>
                    <select wire:model.live="checkoutCheck" class="att-select">
                        <option value="">All</option>
                        <option value="Pulang Sebelum 19:00">Pulang Sebelum 19:00</option>
                        <option value="Pulang 19:00 UP">Pulang 19:00 UP</option>
                        <option value="Clock Out Tidak Ada">Clock Out Tidak Ada</option>
                    </select>
                </div>
            </div>
        </div>

        <div id="activity-check" class="att-section">
            <div class="att-section-header">
                <div class="att-section-title">Activity Check</div>
                <div class="att-section-desc">
                    Hasil pengecekan lokasi, durasi kerja, dan pulang sebelum/di atas jam 19:00.
                </div>
            </div>

            <div class="att-table-wrap">
                <table class="att-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Check Time</th>
                            <th>Type</th>
                            <th>Employee ID</th>
                            <th>Full Name</th>
                            <th>Job Position</th>
                            <th>Shift Name</th>
                            <th>Location Setting Name</th>
                            <th>Location GPS Name</th>
                            <th>Location Address</th>
                            <th>Location Coordinate</th>
                            <th>Description</th>
                            <th>Mobile Flag</th>
                            <th>Status</th>
                            <th class="att-check-head">cek lokasi</th>
                            <th class="att-check-head">cek waktu</th>
                            <th class="att-check-head">Cek Pulang</th>
                            {{-- <th>Matched Location</th>
                            <th>Distance</th> --}}
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($results as $row)
                            <tr>
                                <td class="att-nowrap">{{ $row->attendance_date?->format('Y-m-d') }}</td>
                                <td class="att-nowrap">{{ $row->check_time }}</td>
                                <td class="att-nowrap">{{ $row->check_type }}</td>
                                <td class="att-nowrap">{{ $row->employee_code }}</td>
                                <td class="att-nowrap">{{ $row->employee_name }}</td>
                                <td class="att-nowrap">{{ $row->job_position }}</td>
                                <td class="att-nowrap">{{ $row->shift_name }}</td>
                                <td class="att-nowrap">{{ $row->location_setting_name }}</td>
                                <td class="att-nowrap">{{ $row->location_gps_name }}</td>
                                <td class="att-address">{{ $row->location_address }}</td>
                                <td class="att-nowrap">{{ $row->location_coordinate }}</td>
                                <td class="att-description">{{ $row->description }}</td>
                                <td class="att-nowrap">{{ $row->mobile_flag }}</td>
                                <td class="att-nowrap">{{ $row->approval_status }}</td>

                                <td class="att-nowrap">
                                    @if ($row->location_check === 'Sesuai')
                                        <span class="att-badge att-badge-success">Sesuai</span>
                                    @else
                                        <span class="att-badge att-badge-danger">Tidak Sesuai</span>
                                    @endif
                                </td>

                                <td class="att-nowrap">
                                    <strong>{{ $row->duration_text }}</strong>
                                </td>

                                <td class="att-nowrap">
                                    @if ($row->checkout_check === 'Pulang 19:00 UP')
                                        <span class="att-badge att-badge-warning">{{ $row->checkout_check }}</span>
                                    @elseif ($row->checkout_check === 'Clock Out Tidak Ada')
                                        <span class="att-badge att-badge-danger">{{ $row->checkout_check }}</span>
                                    @else
                                        <span class="att-badge att-badge-gray">{{ $row->checkout_check }}</span>
                                    @endif
                                </td>

                                {{-- <td class="att-nowrap">{{ $row->matched_location_name ?? '-' }}</td>
                                <td class="att-nowrap">{{ $row->distance_meters ? $row->distance_meters . ' m' : '-' }}
                                </td> --}}
                            </tr>
                        @empty
                            <tr>
                                <td colspan="17" style="text-align: center; padding: 28px;">
                                    Belum ada hasil activity.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="att-pagination">
                {{ $results->links() }}
            </div>
        </div>

        <div id="work-hour-summary" class="att-section">
            <div class="att-section-header">
                <div class="att-section-title">Work Hour Summary</div>
                <div class="att-section-desc">
                    Total jam kerja periode {{ $import->period_name }} dari file all time.xlsx.
                </div>
            </div>

            <div class="att-table-wrap">
                <table class="att-table att-summary-table">
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Full Name</th>
                            <th class="att-check-head">{{ $import->period_name }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($workHourSummaries as $row)
                            <tr>
                                <td class="att-nowrap">{{ $row->employee_code }}</td>
                                <td>{{ $row->employee_name }}</td>
                                <td class="att-nowrap"><strong>{{ $row->work_hours_text }}</strong></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 28px;">
                                    Belum ada summary total jam kerja.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="att-pagination">
                {{ $workHourSummaries->links() }}
            </div>
        </div>
    </div>
</x-filament-panels::page>
