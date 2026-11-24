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
                        {{ __('Download the results of Activity Check and Work Hour Summary in 1 file Excel.') }}
                    </div>
                </div>

                @if ($import->status === 'processed')
                    <a href="{{ route('attendance-imports.download', $import) }}" class="att-download-btn">
                        {{ __('Download Excel') }}
                    </a>
                @else
                    <span class="att-badge att-badge-warning">{{ __('Reports are not yet processed') }}</span>
                @endif
            </div>
        </div>

        <div class="att-stats">
            <div class="att-stat-card">
                <div class="att-stat-label">Activity Rows</div>
                <div class="att-stat-value">{{ number_format($stats['results']) }}</div>
            </div>

            <div class="att-stat-card">
                <div class="att-stat-label">{{ __('Matching Location') }}</div>
                <div class="att-stat-value att-stat-success">{{ number_format($stats['location_ok']) }}</div>
            </div>

            <div class="att-stat-card">
                <div class="att-stat-label">{{ __('Does Not Match') }}</div>
                <div class="att-stat-value att-stat-danger">{{ number_format($stats['location_not_ok']) }}</div>
            </div>

            @if ($stats['location_unconfigured'] > 0)
                <div class="att-stat-card">
                    <div class="att-stat-label">{{ __('Location Not Configured') }}</div>
                    <div class="att-stat-value att-stat-warning">
                        {{ number_format($stats['location_unconfigured']) }}
                    </div>
                </div>
            @endif

            <div class="att-stat-card">
                <div class="att-stat-label">{{ __('Clocked Out at or After 19:00') }}</div>
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
                    <label class="att-label">{{ __('Search') }}</label>

                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="text" wire:model="search" wire:keydown.enter="applyFilters"
                            placeholder="{{ __('Search employee, location, address...') }}" class="att-input">

                        <button type="button" wire:click="applyFilters" wire:loading.attr="disabled"
                            class="att-download-btn">
                            {{ __('Search') }}
                        </button>

                        <button type="button" wire:click="clearFilters" wire:loading.attr="disabled"
                            class="att-download-btn">
                            Reset
                        </button>
                    </div>
                </div>

                <div>
                    <label class="att-label">{{ __('Location Check') }}</label>
                    <select wire:model.live="locationCheck" class="att-select">
                        <option value="">{{ __('All') }}</option>
                        <option value="Sesuai">{{ __('Matches') }}</option>
                        <option value="Tidak Sesuai">{{ __('Does Not Match') }}</option>
                        <option value="Lokasi Belum Dikonfigurasi">{{ __('Location Not Configured') }}</option>
                    </select>
                </div>

                <div>
                    <label class="att-label">{{ __('Clock-out Check') }}</label>
                    <select wire:model.live="checkoutCheck" class="att-select">
                        <option value="">{{ __('All') }}</option>
                        <option value="Pulang Sebelum 19:00">{{ __('Clocked Out Before 19:00') }}</option>
                        <option value="Pulang 19:00 UP">{{ __('Clocked Out at or After 19:00') }}</option>
                        <option value="Clock Out Tidak Ada">{{ __('No Clock Out') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <div id="activity-check" class="att-section">
            <div class="att-section-header">
                <div class="att-section-title">Activity Check</div>
                <div class="att-section-desc">
                    Review work locations, shift duration, and whether employees clocked out before 19:00.
                    The report also includes employees with work hours but no activity.
                </div>
            </div>

            <div class="att-table-wrap">
                <table class="att-table">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>Check Time</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Employee ID') }}</th>
                            <th>{{ __('Full Name') }}</th>
                            <th>Job Position</th>
                            <th>Shift Name</th>
                            <th>Location Setting Name</th>
                            <th>Location GPS Name</th>
                            <th>Location Address</th>
                            <th>Location Coordinate</th>
                            <th>{{ __('Description') }}</th>
                            <th>Mobile Flag</th>
                            <th>{{ __('Status') }}</th>
                            <th class="att-check-head">{{ __('check location') }}</th>
                            <th class="att-check-head">{{ __('time check') }}</th>
                            <th class="att-check-head">{{ __('Clock-out Check') }}</th>
                            {{-- <th>Matched Location</th>
                            <th>Distance</th> --}}
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($results as $row)
                            <tr>
                                <td class="att-nowrap">{{ $row->attendance_date?->format('Y-m-d') }}</td>
                                <td class="att-nowrap">{{ $row->check_time }}</td>
                                <td class="att-nowrap">{{ __($row->check_type) }}</td>
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
                                        <span class="att-badge att-badge-success">{{ __('Matches') }}</span>
                                    @elseif ($row->location_check === 'Tidak Ada Activity')
                                        <span class="att-badge att-badge-gray">{{ __('No Activity') }}</span>
                                    @elseif ($row->location_check === 'Lokasi Belum Dikonfigurasi')
                                        <span class="att-badge att-badge-warning">{{ __('Location Not Configured') }}</span>
                                    @else
                                        <span class="att-badge att-badge-danger">{{ __('Does Not Match') }}</span>
                                    @endif
                                </td>

                                <td class="att-nowrap">
                                    <strong>{{ $row->duration_text }}</strong>
                                </td>

                                <td class="att-nowrap">
                                    @if ($row->checkout_check === 'Pulang 19:00 UP')
                                        <span class="att-badge att-badge-warning">{{ __($row->checkout_check) }}</span>
                                    @elseif ($row->checkout_check === 'Clock Out Tidak Ada')
                                        <span class="att-badge att-badge-danger">{{ __($row->checkout_check) }}</span>
                                    @else
                                        <span class="att-badge att-badge-gray">{{ __($row->checkout_check) }}</span>
                                    @endif
                                </td>

                                {{-- <td class="att-nowrap">{{ $row->matched_location_name ?? '-' }}</td>
                                <td class="att-nowrap">{{ $row->distance_meters ? $row->distance_meters . ' m' : '-' }}
                                </td> --}}
                            </tr>
                        @empty
                            <tr>
                                <td colspan="17" style="text-align: center; padding: 28px;">
                                    {{ __('No activity results yet.') }}
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
                    {{ __('Total hours of period work') }} {{ $import->period_name }} {{ __('from all time.xlsx file.') }}
                </div>
            </div>

            <div class="att-table-wrap">
                <table class="att-table att-summary-table">
                    <thead>
                        <tr>
                            <th>{{ __('Employee ID') }}</th>
                            <th>{{ __('Full Name') }}</th>
                            <th class="att-check-head">{{ $import->period_name }}</th>
                            <th>{{ __('Data Status') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($workHourSummaries as $row)
                            <tr>
                                <td class="att-nowrap">{{ $row->employee_code }}</td>
                                <td>{{ $row->employee_name }}</td>
                                <td class="att-nowrap"><strong>{{ $row->work_hours_text }}</strong></td>
                                <td class="att-nowrap">
                                    @if (data_get($row->raw_data, 'status') === 'Tidak Ada Total Jam Kerja')
                                        <span class="att-badge att-badge-warning">{{ __('No Work Hours Recorded') }}</span>
                                    @else
                                        <span class="att-badge att-badge-success">{{ __('Available') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 28px;">
                                    {{ __('There\'s no total summary of working hours.') }}
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
