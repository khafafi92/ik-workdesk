<x-filament-panels::page>
    @php
        $imports = $this->getImports();
        $selectedImport = $this->selectedImport;
    @endphp

    <div class="att-page att-report-center">
        <section class="att-section" aria-labelledby="attendance-history-title">
            <div class="att-section-header">
                <h2 id="attendance-history-title" class="att-section-title">{{ __('Report history') }}</h2>
                <p class="att-section-desc">{{ __('Select uploaded reports for status and results.') }}</p>
            </div>
            @if ($imports->isNotEmpty())
                <label for="attendance-period" class="att-label">{{ __('Report period') }}</label>
                <select id="attendance-period" wire:model.live="attendanceImportId" class="att-select">
                    @foreach ($imports as $import)
                        <option value="{{ $import->id }}">{{ $import->period_name }} ({{ $this->statusLabel($import->status) }})</option>
                    @endforeach
                </select>
                <p wire:loading wire:target="attendanceImportId" role="status" class="att-section-desc">{{ __('Loading report...') }}</p>
            @else
                <p>{{ __('No attendance reports yet.') }}</p>
                @if (\App\Filament\Resources\AttendanceImports\AttendanceImportResource::canCreate())
                    <p class="att-section-desc">{{ __('Start with the new data upload button, then select the location file absent and the total working hour.') }}</p>
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
                    <p>{{ __('Reports are ready. and a total workday.') }}</p>
                    <div class="att-report-actions">
                        <x-filament::button tag="a" :href="\App\Filament\Resources\AttendanceImports\AttendanceImportResource::getUrl('results', ['record' => $selectedImport])">{{ __('View report results') }}</x-filament::button>
                        @if (auth()->user()?->hasPermission('attendance.view'))
                            <x-filament::button tag="a" color="gray" :href="url('/attendance-imports/'.$selectedImport->id.'/download')">{{ __('Download Excel') }}</x-filament::button>
                        @endif
                    </div>
                    <p class="att-section-desc">{{ __('In Process') }} {{ $selectedImport->processed_at?->format('d M Y H:i') ?? '-' }}</p>
                @elseif ($selectedImport->status === 'processing')
                    <p role="status" wire:poll.5s>{{ __('Files are being processed. Results will be available once the process is completed.') }}</p>
                @else
                    @if ($selectedImport->status === 'failed')
                        <p role="alert">{{ __('The process is not yet successful.') }} {{ $selectedImport->notes }}</p>
                    @else
                        <p>{{ __('File saved and unprocessed.') }}</p>
                    @endif
                    <div class="att-report-actions">
                        {{ $this->processAction }}
                        @if (\App\Filament\Resources\AttendanceImports\AttendanceImportResource::canEdit($selectedImport))
                            <x-filament::button tag="a" color="gray" :href="\App\Filament\Resources\AttendanceImports\AttendanceImportResource::getUrl('edit', ['record' => $selectedImport])">{{ __('Fix files') }}</x-filament::button>
                        @endif
                    </div>
                    @unless (auth()->user()?->hasPermission('attendance.manage'))
                        <p class="att-section-desc">{{ __('Waiting for the user with an attendance process permit.') }}</p>
                    @endunless
                @endif
                <details class="att-report-files">
                    <summary>{{ __('Source files') }}</summary>
                    <p><strong>{{ __('Attendance location:') }}</strong> {{ $selectedImport->attendance_file_name ?? '-' }}</p>
                    <p><strong>{{ __('Total working hours:') }}</strong> {{ $selectedImport->work_hour_file_name ?? '-' }}</p>
                </details>
            </section>
        @elseif ($imports->isNotEmpty())
            <p role="status">{{ __('Report not found. Select another period.') }}</p>
        @endif
    </div>
</x-filament-panels::page>
