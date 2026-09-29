<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\DailyReport;
use Livewire\Component;

/**
 * Komponen halaman /daily-input.
 *
 * Alur belajarnya:
 * 1. View: resources/views/livewire/daily-input.blade.php menampilkan worksheet input harian.
 * 2. Property public di class ini menjadi state form Livewire melalui wire:model.
 * 3. save() menyimpan/mengupdate data ke tabel daily_reports.
 * 4. Data reading engine/compressor disimpan sebagai array JSON pada kolom engine_values dan compressor_values.
 */
class DailyInput extends Component
{
    public array $unitOptions = ['A', 'B', 'C'];

    // Kolom jam pada worksheet. Key dipakai sebagai nama index array yang disimpan ke database.
    public array $timeColumns = [
        ['key' => 't0200', 'label' => '02:00'],
        ['key' => 't0400', 'label' => '04:00'],
        ['key' => 't0600', 'label' => '06:00'],
        ['key' => 't0800', 'label' => '08:00'],
        ['key' => 't1000', 'label' => '10:00'],
        ['key' => 't1200', 'label' => '12:00'],
        ['key' => 't1400', 'label' => '14:00'],
        ['key' => 't1600', 'label' => '16:00'],
        ['key' => 't1800', 'label' => '18:00'],
        ['key' => 't2000', 'label' => '20:00'],
        ['key' => 't2200', 'label' => '22:00'],
        ['key' => 't2400', 'label' => '24:00'],
    ];

    // Daftar parameter ini dipakai view untuk membentuk baris tabel Engine dan Compressor.
    public array $engineParameters = [];

    public array $compressorParameters = [];

    // Nilai reading harian: dailyValues[engine/compressor][nomor_parameter][jam].
    public array $dailyValues = [
        'engine' => [],
        'compressor' => [],
    ];

    public array $average_notes = [];

    public $selectedUnit = '';

    public $report_date = '';

    public $operator_day = '';

    public $operator_night = '';

    public $activity = '';

    public $running_hours = '';

    public $standby_hours = '';

    public $down_reactive_hours = '';

    public $shutdown_indication = '';

    public $last_stock_oil = '';

    public $received_oil = '';

    public $used_oil = '';

    public $remark_used_oil = '';

    public ?int $reportId = null;

    public function mount(?int $reportId = null): void
    {
        // mount() berjalan saat halaman dibuka. Jika ada reportId, berarti mode edit.
        $this->reportId = $reportId;
        $this->selectedUnit = '';
        $this->report_date = now()->format('Y-m-d');
        $this->engineParameters = $this->buildEngineParameters();
        $this->compressorParameters = $this->buildCompressorParameters();
        $this->initializeDailyValues();

        if ($this->reportId) {
            $this->loadReportById($this->reportId);
        } else {
            $this->loadExistingReport();
        }
    }

    public function save(): void
    {
        // Validasi input sebelum data boleh masuk ke tabel daily_reports.
        $this->validate([
            'selectedUnit' => 'required|in:A,B,C',
            'report_date' => 'required|date',
            'operator_day' => 'nullable|string|max:255',
            'operator_night' => 'nullable|string|max:255',
            'activity' => 'nullable|string|max:2000',
            'running_hours' => 'nullable|numeric|min:0',
            'standby_hours' => 'nullable|numeric|min:0',
            'down_reactive_hours' => 'nullable|numeric|min:0',
            'shutdown_indication' => 'nullable|string|max:2000',
            'last_stock_oil' => 'nullable|numeric|min:0',
            'received_oil' => 'nullable|numeric|min:0',
            'used_oil' => 'nullable|numeric|min:0',
            'remark_used_oil' => 'nullable|string|max:2000',
            'average_notes.*' => 'nullable|string|max:500',
            'dailyValues.engine.*.*' => 'nullable|string|max:50',
            'dailyValues.compressor.*.*' => 'nullable|string|max:50',
        ], [
            'selectedUnit.required' => 'Unit harus dipilih.',
            'selectedUnit.in' => 'Unit yang dipilih tidak valid.',
        ]);

        // Ambil report berdasarkan kombinasi unit + tanggal. Jika belum ada, buat object baru.
        $report = DailyReport::firstOrNew([
            'unit_code' => $this->selectedUnit,
            'report_date' => $this->report_date,
        ]);
        $isNewReport = ! $report->exists;

        // Mapping field form ke kolom database daily_reports.
        // engine_values dan compressor_values adalah array besar yang Laravel cast ke JSON.
        $report->fill([
            'operator_day' => $this->operator_day ?: null,
            'operator_night' => $this->operator_night ?: null,
            'updated_by_user_id' => auth()->id(),
            'engine_values' => $this->dailyValues['engine'],
            'compressor_values' => $this->dailyValues['compressor'],
            'engine_average' => $this->getEngineAverageProperty(),
            'compressor_average' => $this->getCompressorAverageProperty(),
            'combined_average' => $this->getCombinedAverageProperty(),
            'running_hours' => $this->nullableNumber($this->running_hours),
            'standby_hours' => $this->nullableNumber($this->standby_hours),
            'down_reactive_hours' => $this->nullableNumber($this->down_reactive_hours),
            'shutdown_indication' => $this->shutdown_indication ?: null,
            'last_stock_oil' => $this->nullableNumber($this->last_stock_oil),
            'received_oil' => $this->nullableNumber($this->received_oil),
            'used_oil' => $this->nullableNumber($this->used_oil),
            'remark_used_oil' => $this->remark_used_oil ?: null,
            'average_notes' => collect($this->average_notes)
                ->map(fn ($value) => is_string($value) ? trim($value) : $value)
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->all(),
            'activity' => $this->activity ?: null,
        ]);

        if ($isNewReport || ! $report->created_by_user_id) {
            $report->created_by_user_id = auth()->id();
        }

        // INSERT atau UPDATE ke tabel daily_reports.
        $report->save();

        // Catat aktivitas user supaya halaman logs bisa menampilkan jejak perubahan.
        ActivityLog::write(
            $isNewReport ? 'daily_input' : 'daily_edit',
            'daily',
            'Daily report Unit '.$this->selectedUnit.' tanggal '.$this->report_date.' '.($isNewReport ? 'dibuat.' : 'diupdate.')
        );

        $this->reportId = $report->id;
        $this->loadExistingReport();

        $message = $isNewReport ? 'berhasil disimpan.' : 'berhasil diupdate.';
        $readingCount = $this->filledReadingCount();

        session()->flash('success', 'Input daily Unit '.$this->selectedUnit.' tanggal '.$this->report_date.' '.$message.' '.$readingCount.' cell reading tersimpan.');
    }

    public function updatedSelectedUnit(): void
    {
        // Saat user mengganti unit, sistem cek apakah unit+tanggal itu sudah punya report.
        $this->loadExistingReport();
    }

    public function updatedReportDate(): void
    {
        // Saat tanggal berubah, worksheet akan diisi ulang dari data existing jika ada.
        $this->loadExistingReport();
    }

    public function loadReport(int $reportId): void
    {
        // Dipakai ketika user memilih report tertentu untuk dibuka/edit.
        $this->reportId = $reportId;
        $this->loadReportById($reportId);
    }

    public function pasteReadingBlock(string $section, int $startRow, int $startCol, array $rows): void
    {
        // Fitur paste dari Excel: data ditempel ke cell worksheet berdasarkan row/column target.
        if (! in_array($section, ['engine', 'compressor'], true)) {
            return;
        }

        $timeKeys = collect($this->timeColumns)->pluck('key')->values();
        $maxRow = $section === 'engine'
            ? count($this->engineParameters)
            : count($this->compressorParameters);

        foreach ($rows as $rowOffset => $cells) {
            $targetRow = $startRow + (int) $rowOffset;

            if ($targetRow < 1 || $targetRow > $maxRow || ! is_array($cells)) {
                continue;
            }

            foreach ($cells as $colOffset => $value) {
                $targetCol = $startCol + (int) $colOffset;
                $timeKey = $timeKeys->get($targetCol);

                if (! $timeKey) {
                    continue;
                }

                $this->dailyValues[$section][$targetRow][$timeKey] = is_scalar($value)
                    ? trim((string) $value)
                    : '';
            }
        }
    }

    public function getEngineAverageProperty(): ?float
    {
        // Computed property Livewire: bisa dipanggil dari view sebagai $this->engineAverage.
        return $this->sectionAverage('engine');
    }

    public function getCompressorAverageProperty(): ?float
    {
        return $this->sectionAverage('compressor');
    }

    public function getCombinedAverageProperty(): ?float
    {
        $values = collect([
            ...$this->numericValuesForSection('engine'),
            ...$this->numericValuesForSection('compressor'),
        ]);

        if ($values->isEmpty()) {
            return null;
        }

        return round($values->average(), 2);
    }

    public function getEngineCellCountProperty(): int
    {
        return count($this->engineParameters) * count($this->timeColumns);
    }

    public function getCompressorCellCountProperty(): int
    {
        return count($this->compressorParameters) * count($this->timeColumns);
    }

    public function getAveragePanelRowsProperty(): array
    {
        return [
            ['label' => 'Suction Header', 'value' => $this->fixedSampleAverage('compressor', 1), 'unit' => 'Psig'],
            ['label' => 'Suction Compressor', 'value' => $this->fixedSampleAverage('compressor', 4), 'unit' => 'Psig'],
            ['label' => 'Discharge Header', 'value' => $this->fixedSampleAverage('compressor', 2), 'unit' => 'Psig'],
            ['label' => 'Discharge Compressor', 'value' => $this->fixedSampleAverage('compressor', 5), 'unit' => 'Psig'],
            ['label' => 'Field Temperature', 'value' => $this->fixedSampleAverage('compressor', 3), 'unit' => 'F'],
            ['label' => 'Oil Temp Compressor', 'value' => $this->fixedSampleAverage('compressor', 12), 'unit' => 'F'],
            ['label' => 'Oil Temp Engine', 'value' => $this->fixedSampleAverage('engine', 7), 'unit' => 'F'],
            ['label' => 'Coolant Temp EJW', 'value' => $this->fixedSampleAverage('engine', 6), 'unit' => 'F'],
            ['label' => 'Exhaust Temp Right', 'value' => $this->fixedSampleAverage('engine', 11), 'unit' => 'F'],
            ['label' => 'Exhaust Temp Left', 'value' => $this->fixedSampleAverage('engine', 12), 'unit' => 'F'],
            ['label' => 'Speed Engine', 'value' => $this->fixedSampleAverage('engine', 2), 'unit' => 'RPM'],
            ['label' => 'Engine Load', 'value' => $this->fixedSampleAverage('engine', 9), 'unit' => '%'],
        ];
    }

    public function getAveragePanelExtraRowsProperty(): array
    {
        return [
            ['label' => 'Suction Scrubber Temp', 'value' => $this->rowAverage('compressor', 7), 'unit' => 'F'],
            ['label' => 'STN Disch Temp', 'value' => $this->rowAverage('compressor', 10), 'unit' => 'F'],
        ];
    }

    public function getGasFlowRateAverageProperty(): ?float
    {
        return $this->fixedSampleAverage('compressor', 21);
    }

    public function getTotalHoursProperty(): ?float
    {
        $values = collect([
            $this->nullableNumber($this->running_hours),
            $this->nullableNumber($this->standby_hours),
            $this->nullableNumber($this->down_reactive_hours),
        ])->filter(fn ($value) => $value !== null);

        if ($values->isEmpty()) {
            return null;
        }

        return round($values->sum(), 2);
    }

    public function getOilStockProperty(): ?float
    {
        $lastStockOil = $this->nullableNumber($this->last_stock_oil);
        $receivedOil = $this->nullableNumber($this->received_oil);
        $usedOil = $this->nullableNumber($this->used_oil);

        if ($lastStockOil === null && $receivedOil === null && $usedOil === null) {
            return null;
        }

        return round(($lastStockOil ?? 0) + ($receivedOil ?? 0) - ($usedOil ?? 0), 2);
    }

    public function getCompressorAverageCellsProperty(): array
    {
        return [
            1 => ['label' => 'Stand by :', 'value' => null, 'unit' => 'Hours', 'class' => ''],
            2 => ['label' => 'Down Reactive :', 'value' => null, 'unit' => 'Hours', 'class' => ''],
            3 => ['label' => 'Total :', 'value' => $this->getTotalHoursProperty(), 'unit' => 'Hours', 'class' => ''],
            5 => ['label' => 'Gas Flow Rate :', 'value' => $this->getGasFlowRateAverageProperty(), 'unit' => 'MMSCFD', 'class' => ''],
            6 => ['label' => 'Shutdown Time & Indication :', 'value' => null, 'unit' => '', 'class' => ''],
            7 => ['label' => '', 'value' => $this->getAveragePanelExtraRowsProperty()[0]['value'] ?? null, 'unit' => '', 'class' => ''],
            10 => ['label' => '', 'value' => $this->getAveragePanelExtraRowsProperty()[1]['value'] ?? null, 'unit' => '', 'class' => ''],
            12 => ['label' => 'Last Stock Oil', 'value' => null, 'unit' => 'Liter', 'class' => ''],
            13 => ['label' => 'Recived Oil', 'value' => null, 'unit' => 'Liter', 'class' => ''],
            14 => ['label' => 'Used Oil', 'value' => null, 'unit' => 'Liter', 'class' => ''],
            15 => ['label' => 'Stock', 'value' => $this->getOilStockProperty(), 'unit' => 'Liter', 'class' => ''],
            18 => ['label' => 'Remark Used Oil :', 'value' => null, 'unit' => '', 'class' => ''],
        ];
    }

    public function rowAverage(string $section, int $number): ?float
    {
        // Rata-rata satu baris parameter, misalnya Oil Temp atau Discharge Pressure.
        $rowValues = $this->dailyValues[$section][$number] ?? [];
        $values = collect($rowValues)
            ->map(fn ($value) => $this->toNumericValue($value))
            ->filter(fn ($value) => $value !== null);

        if ($values->isEmpty()) {
            return null;
        }

        return round($values->average(), 2);
    }

    private function fixedSampleAverage(string $section, int $number): ?float
    {
        $rowValues = $this->dailyValues[$section][$number] ?? [];
        $values = collect($rowValues)
            ->map(fn ($value) => $this->toNumericValue($value))
            ->filter(fn ($value) => $value !== null);

        return round($values->sum() / count($this->timeColumns), 2);
    }

    private function sectionAverage(string $section): ?float
    {
        $values = collect($this->numericValuesForSection($section));

        if ($values->isEmpty()) {
            return null;
        }

        return round($values->average(), 2);
    }

    private function numericValuesForSection(string $section): array
    {
        $values = [];

        foreach ($this->dailyValues[$section] ?? [] as $rowValues) {
            foreach ($rowValues as $value) {
                $numericValue = $this->toNumericValue($value);

                if ($numericValue !== null) {
                    $values[] = $numericValue;
                }
            }
        }

        return $values;
    }

    private function filledReadingCount(): int
    {
        return collect($this->dailyValues['engine'] ?? [])
            ->merge($this->dailyValues['compressor'] ?? [])
            ->flatten()
            ->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
            ->count();
    }

    private function initializeDailyValues(): void
    {
        // Membuat struktur array kosong untuk semua parameter dan semua jam.
        $timeKeys = collect($this->timeColumns)->pluck('key')->all();
        $this->dailyValues = [
            'engine' => [],
            'compressor' => [],
        ];

        foreach ($this->engineParameters as $parameter) {
            $this->dailyValues['engine'][$parameter['number']] = array_fill_keys($timeKeys, '');
        }

        foreach ($this->compressorParameters as $parameter) {
            $this->dailyValues['compressor'][$parameter['number']] = array_fill_keys($timeKeys, '');
            $this->average_notes[$parameter['number']] = $this->average_notes[$parameter['number']] ?? '';
        }
    }

    private function loadExistingReport(): void
    {
        // Query SELECT ke daily_reports berdasarkan unit dan tanggal yang dipilih.
        if (! $this->selectedUnit || ! $this->report_date) {
            return;
        }

        $report = DailyReport::query()
            ->where('unit_code', $this->selectedUnit)
            ->whereDate('report_date', $this->report_date)
            ->first();

        $this->initializeDailyValues();

        // Jika belum ada data di database, form dikosongkan untuk input baru.
        if (! $report) {
            $this->operator_day = '';
            $this->operator_night = '';
            $this->prefillCurrentOperatorForShift();
            $this->activity = '';
            $this->resetAveragePanelFields();

            return;
        }

        // Jika data ditemukan, array JSON dari database dimasukkan lagi ke worksheet.
        foreach ($report->engine_values ?? [] as $number => $values) {
            if (isset($this->dailyValues['engine'][$number]) && is_array($values)) {
                $this->dailyValues['engine'][$number] = array_replace($this->dailyValues['engine'][$number], $values);
            }
        }

        foreach ($report->compressor_values ?? [] as $number => $values) {
            if (isset($this->dailyValues['compressor'][$number]) && is_array($values)) {
                $this->dailyValues['compressor'][$number] = array_replace($this->dailyValues['compressor'][$number], $values);
            }
        }

        $this->operator_day = $report->operator_day ?? '';
        $this->operator_night = $report->operator_night ?? '';
        $this->activity = $report->activity ?? '';
        $this->running_hours = $report->running_hours ?? '';
        $this->standby_hours = $report->standby_hours ?? '';
        $this->down_reactive_hours = $report->down_reactive_hours ?? '';
        $this->shutdown_indication = $report->shutdown_indication ?? '';
        $this->last_stock_oil = $report->last_stock_oil ?? '';
        $this->received_oil = $report->received_oil ?? '';
        $this->used_oil = $report->used_oil ?? '';
        $this->remark_used_oil = $report->remark_used_oil ?? '';
        $this->average_notes = array_replace($this->average_notes, $report->average_notes ?? []);
    }

    private function loadReportById(int $reportId): void
    {
        // Query report spesifik dari tombol edit/detail.
        $report = DailyReport::find($reportId);

        if (! $report) {
            return;
        }

        $this->selectedUnit = $report->unit_code;
        $this->report_date = $report->report_date?->format('Y-m-d') ?? now()->format('Y-m-d');
        $this->loadExistingReport();
    }

    private function toNumericValue(mixed $value): ?float
    {
        // Konversi input cell menjadi angka agar bisa dihitung average.
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);

        if ($value === '' || $value === '-') {
            return null;
        }

        $normalized = str_replace([',', ' '], '', $value);

        if (str_ends_with($normalized, '%')) {
            $normalized = rtrim($normalized, '%');
        }

        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function nullableNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function resetAveragePanelFields(): void
    {
        $this->running_hours = '';
        $this->standby_hours = '';
        $this->down_reactive_hours = '';
        $this->shutdown_indication = '';
        $this->last_stock_oil = '';
        $this->received_oil = '';
        $this->used_oil = '';
        $this->remark_used_oil = '';
        $this->average_notes = [];
    }

    private function prefillCurrentOperatorForShift(): void
    {
        $operatorName = auth()->user()?->name;

        if (! $operatorName) {
            return;
        }

        $hour = now(config('app.timezone'))->hour;

        if ($hour >= 6 && $hour < 18) {
            $this->operator_day = $this->operator_day ?: $operatorName;

            return;
        }

        $this->operator_night = $this->operator_night ?: $operatorName;
    }

    private function buildEngineParameters(): array
    {
        // Master baris Engine. Jika ingin tambah parameter Engine, tambahkan item di array ini.
        return [
            ['number' => 1, 'description' => 'Running Hour', 'unit' => 'Hours', 'low_alarm' => '-', 'high_alarm' => '-', 'normal_range' => '-'],
            ['number' => 2, 'description' => 'Average RPM (ESM5A501..)', 'unit' => 'RPM', 'low_alarm' => '-', 'high_alarm' => '1220-1250', 'normal_range' => '900-1200'],
            ['number' => 3, 'description' => 'Oil Press (ESM5A501..)', 'unit' => 'Psig', 'low_alarm' => '30-20', 'high_alarm' => '-', 'normal_range' => '50-70'],
            ['number' => 4, 'description' => 'IMAP (LEFT)', 'unit' => 'in Hg', 'low_alarm' => '25', 'high_alarm' => '45', 'normal_range' => '30-40'],
            ['number' => 5, 'description' => 'IMAP (RIGHT)', 'unit' => 'in Hg', 'low_alarm' => '25', 'high_alarm' => '45', 'normal_range' => '30-40'],
            ['number' => 6, 'description' => 'Coolant Temp (TX505..)', 'unit' => 'F', 'low_alarm' => '-', 'high_alarm' => '190-200', 'normal_range' => '180-190'],
            ['number' => 7, 'description' => 'Oil Temp', 'unit' => 'F', 'low_alarm' => '-', 'high_alarm' => '196-205', 'normal_range' => '160-195'],
            ['number' => 8, 'description' => 'INT MFD Tempr', 'unit' => 'F', 'low_alarm' => '-', 'high_alarm' => '145-150', 'normal_range' => '90-140'],
            ['number' => 9, 'description' => 'Engine Load', 'unit' => '%', 'low_alarm' => '100', 'high_alarm' => '110', 'normal_range' => '80-95'],
            ['number' => 10, 'description' => 'Desired Timing', 'unit' => 'btdc', 'low_alarm' => '-', 'high_alarm' => '-', 'normal_range' => '20-30'],
            ['number' => 11, 'description' => 'Right Exhaust Temp (TE 610R..)', 'unit' => 'F', 'low_alarm' => '-', 'high_alarm' => '1120-1184', 'normal_range' => '950-1150'],
            ['number' => 12, 'description' => 'Left Exhaust Temp (TE 610L..)', 'unit' => 'F', 'low_alarm' => '-', 'high_alarm' => '1120-1184', 'normal_range' => '950-1150'],
            ['number' => 13, 'description' => 'Jacket Water IN Tempr (TI332..)', 'unit' => 'F', 'low_alarm' => '-', 'high_alarm' => '-', 'normal_range' => '150-180'],
            ['number' => 14, 'description' => 'Jacket Water OUT Tempr (TI331..)', 'unit' => 'F', 'low_alarm' => '-', 'high_alarm' => '-', 'normal_range' => '170-200'],
            ['number' => 15, 'description' => 'AUX Water IN Tempr (TI332..)', 'unit' => 'F', 'low_alarm' => '-', 'high_alarm' => '-', 'normal_range' => '110-145'],
            ['number' => 16, 'description' => 'AUX Water OUT Tempr (TI331..)', 'unit' => 'F', 'low_alarm' => '-', 'high_alarm' => '-', 'normal_range' => '120-180'],
        ];
    }

    private function buildCompressorParameters(): array
    {
        // Master baris Compressor. Jika ingin tambah parameter Compressor, tambahkan item di array ini.
        return [
            ['number' => 1, 'description' => 'Suction Header Press', 'unit' => 'Psig', 'low_alarm' => '-', 'high_alarm' => '-', 'normal_range' => '170-250'],
            ['number' => 2, 'description' => 'Discharge Header Press', 'unit' => 'Psig', 'low_alarm' => '-', 'high_alarm' => '-', 'normal_range' => '400-550'],
            ['number' => 3, 'description' => 'Field Temperature', 'unit' => 'F', 'low_alarm' => '-', 'high_alarm' => '-', 'normal_range' => '70-130'],
            ['number' => 4, 'description' => 'Suction Press (PIA 101..)', 'unit' => 'Psig', 'low_alarm' => '135-125', 'high_alarm' => '270-290', 'normal_range' => '185-255'],
            ['number' => 5, 'description' => 'Discharge Press', 'unit' => 'Psig', 'low_alarm' => '-', 'high_alarm' => '550-565', 'normal_range' => '400-550'],
            ['number' => 6, 'description' => 'STN Discharge Pressure (PIA 221..)', 'unit' => 'Psig', 'low_alarm' => '-', 'high_alarm' => '550-565', 'normal_range' => '400-550'],
            ['number' => 7, 'description' => 'Suction Scrubber Tempr (TE 11C..)', 'unit' => 'F', 'low_alarm' => '-', 'high_alarm' => '150-160', 'normal_range' => '70-120'],
            ['number' => 8, 'description' => 'Disch Cyl 1 Tempr', 'unit' => 'F', 'low_alarm' => '-', 'high_alarm' => '315-330', 'normal_range' => '170-310'],
            ['number' => 9, 'description' => 'Disch Cyl 2 Tempr', 'unit' => 'F', 'low_alarm' => '-', 'high_alarm' => '315-330', 'normal_range' => '170-310'],
            ['number' => 10, 'description' => 'STN Disch Temp (TIA 233..)', 'unit' => 'F', 'low_alarm' => '-', 'high_alarm' => '150-170', 'normal_range' => '70-125'],
            ['number' => 11, 'description' => 'Compr Oil Press (PI-404..)', 'unit' => 'Psig', 'low_alarm' => '50-45', 'high_alarm' => '-', 'normal_range' => '45-60'],
            ['number' => 12, 'description' => 'Compr Oil Tempr (TEA 402..)', 'unit' => 'F', 'low_alarm' => '-', 'high_alarm' => '180-190', 'normal_range' => '150-180'],
            ['number' => 13, 'description' => 'Instr Air Press (PI 102..)', 'unit' => 'Psig', 'low_alarm' => '80', 'high_alarm' => '-', 'normal_range' => '100-120'],
            ['number' => 14, 'description' => 'Fuel Gas Press (PIA 604..)', 'unit' => 'Psig', 'low_alarm' => '25-20', 'high_alarm' => '-', 'normal_range' => '45-60'],
            ['number' => 15, 'description' => 'Suction Scrubber Tempr (TI 110..)', 'unit' => 'F', 'low_alarm' => '-', 'high_alarm' => '-', 'normal_range' => '70-100'],
            ['number' => 16, 'description' => 'Suction Scrubber Level (LG 110..)', 'unit' => 'Inch', 'low_alarm' => '-', 'high_alarm' => '-', 'normal_range' => '0.5"'],
            ['number' => 17, 'description' => 'Discharge Scrubber Tempr (TI 220..)', 'unit' => 'F', 'low_alarm' => '-', 'high_alarm' => '-', 'normal_range' => '70-120'],
            ['number' => 18, 'description' => 'Discharge Scrubber Level (LG 221..)', 'unit' => 'Inch', 'low_alarm' => '-', 'high_alarm' => '-', 'normal_range' => '0.5"'],
            ['number' => 19, 'description' => 'Level Oil Tank (T410 & T420)', 'unit' => '%', 'low_alarm' => '-', 'high_alarm' => '-', 'normal_range' => '40-90'],
            ['number' => 20, 'description' => 'Level Water Tank EJW & AUX', 'unit' => '%', 'low_alarm' => '65', 'high_alarm' => '-', 'normal_range' => '70-100'],
            ['number' => 21, 'description' => 'Flow Rate Gas', 'unit' => 'MMSCFD', 'low_alarm' => '-', 'high_alarm' => '-', 'normal_range' => '-'],
            ['number' => 22, 'description' => 'Battery Proflow', 'unit' => '%', 'low_alarm' => '-', 'high_alarm' => '-', 'normal_range' => '20-100'],
            ['number' => 23, 'description' => 'Recycle Valve (Open / Close)', 'unit' => '%', 'low_alarm' => '-', 'high_alarm' => '-', 'normal_range' => '-'],
            ['number' => 24, 'description' => 'Weather (Cerah / Terik / Hujan)', 'unit' => '-', 'low_alarm' => '-', 'high_alarm' => '-', 'normal_range' => '-'],
        ];
    }

    public function render()
    {
        // Data yang dihitung di sini dikirim ke view livewire.daily-input untuk ditampilkan.
        $engineAverage = $this->getEngineAverageProperty();
        $compressorAverage = $this->getCompressorAverageProperty();
        $combinedAverage = $this->getCombinedAverageProperty();
        $engineCellCount = $this->getEngineCellCountProperty();
        $compressorCellCount = $this->getCompressorCellCountProperty();
        $averagePanelRows = $this->getAveragePanelRowsProperty();
        $averagePanelExtraRows = $this->getAveragePanelExtraRowsProperty();
        $gasFlowRateAverage = $this->getGasFlowRateAverageProperty();
        $totalHours = $this->getTotalHoursProperty();
        $oilStock = $this->getOilStockProperty();
        $compressorAverageCells = $this->getCompressorAverageCellsProperty();

        return view('livewire.daily-input', compact(
            'engineAverage',
            'compressorAverage',
            'combinedAverage',
            'engineCellCount',
            'compressorCellCount',
            'averagePanelRows',
            'averagePanelExtraRows',
            'gasFlowRateAverage',
            'totalHours',
            'oilStock',
            'compressorAverageCells',
        ))->layout('components.layouts.app');
    }
}
