<?php

namespace App\Livewire;

use App\Models\AvailabilityLtro1bRecord;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Livewire\Component;

class AvailabilityLtro1b extends Component
{
    public string $selectedPeriodStart = '';

    public string $newPeriodEndMonth = '';

    public array $rows = [];

    private array $numericFields = [
        'flow_rate',
        'fuel_gas_consumption',
        'spare_part_availability',
        'comp_a_unplanned_shutdown_hours',
        'comp_a_shutdown_hours',
        'comp_a_running_hours',
        'comp_b_unplanned_shutdown_hours',
        'comp_b_shutdown_hours',
        'comp_b_running_hours',
        'comp_c_unplanned_shutdown_hours',
        'comp_c_shutdown_hours',
        'comp_c_running_hours',
        'total_required_hours',
        'availability_system_capacity',
        'lpo',
        'reliability_percent',
        'availability_percent',
        'doe_percent',
        'pk100_shutdown_hours',
        'ltrx_a_running_hours',
        'ltrx_b_running_hours',
        'pk101_running_hours',
    ];

    public function mount(): void
    {
        $this->selectedPeriodStart = $this->latestPeriodStart();

        if ($this->selectedPeriodStart) {
            $this->loadRows();
        }
    }

    public function updatedSelectedPeriodStart(): void
    {
        $this->loadRows();
    }

    public function createPeriod(): void
    {
        $this->validate([
            'newPeriodEndMonth' => 'required|date_format:Y-m',
        ]);

        $periodEnd = Carbon::createFromFormat('Y-m-d', $this->newPeriodEndMonth.'-20')->startOfDay();
        $periodStart = $periodEnd->copy()->subMonthNoOverflow()->day(21);

        $rowNo = 1;
        foreach (CarbonPeriod::create($periodStart, $periodEnd) as $date) {
            AvailabilityLtro1bRecord::query()->firstOrCreate(
                [
                    'period_start' => $periodStart->toDateString(),
                    'report_date' => $date->toDateString(),
                ],
                [
                    'period_end' => $periodEnd->toDateString(),
                    'row_no' => $rowNo,
                    'day_name' => $date->format('l'),
                    'total_required_hours' => 72,
                ]
            );

            $rowNo++;
        }

        $this->selectedPeriodStart = $periodStart->toDateString();
        $this->newPeriodEndMonth = '';
        $this->loadRows();

        session()->flash('success', 'Period availability berhasil dibuat.');
    }

    public function save(): void
    {
        foreach ($this->rows as $row) {
            $record = AvailabilityLtro1bRecord::query()->find($row['id']);

            if (! $record) {
                continue;
            }

            $values = [
                'rental_code' => $row['rental_code'] ?: null,
                'remark' => $row['remark'] ?: null,
            ];

            foreach ($this->numericFields as $field) {
                $values[$field] = $this->nullableNumber($row[$field] ?? null);
            }

            $record->update($values);
        }

        $this->loadRows();
        session()->flash('success', 'Data Availability LTRO-1B berhasil disimpan.');
    }

    public function deleteRow(int $recordId): void
    {
        $record = AvailabilityLtro1bRecord::query()->find($recordId);

        if (! $record) {
            return;
        }

        $periodStart = $record->period_start->format('Y-m-d');

        $record->delete();
        $this->renumberPeriodRows($periodStart);

        $this->selectedPeriodStart = $periodStart;
        $this->loadRows();

        session()->flash('success', 'Baris availability berhasil dihapus.');
    }

    public function deletePeriod(): void
    {
        if (! $this->selectedPeriodStart) {
            return;
        }

        AvailabilityLtro1bRecord::query()
            ->whereDate('period_start', $this->selectedPeriodStart)
            ->delete();

        $this->selectedPeriodStart = $this->latestPeriodStart();
        $this->loadRows();

        session()->flash('success', 'Period availability berhasil dihapus.');
    }

    public function render()
    {
        $periods = AvailabilityLtro1bRecord::query()
            ->select(['period_start', 'period_end'])
            ->distinct()
            ->orderByDesc('period_start')
            ->get()
            ->map(fn (AvailabilityLtro1bRecord $record) => [
                'value' => $record->period_start->format('Y-m-d'),
                'label' => $record->period_start->format('d M Y').' - '.$record->period_end->format('d M Y'),
            ]);

        $summary = $this->summary();

        return view('livewire.availability-ltro-1b', compact('periods', 'summary'))
            ->layout('components.layouts.app');
    }

    private function loadRows(): void
    {
        if (! $this->selectedPeriodStart) {
            $this->rows = [];

            return;
        }

        $this->rows = AvailabilityLtro1bRecord::query()
            ->whereDate('period_start', $this->selectedPeriodStart)
            ->orderBy('report_date')
            ->get()
            ->map(fn (AvailabilityLtro1bRecord $record) => [
                'id' => $record->id,
                'row_no' => $record->row_no,
                'day_name' => $record->day_name,
                'report_date' => $record->report_date->format('Y-m-d'),
                'flow_rate' => $this->numberForInput($record->flow_rate),
                'rental_code' => $record->rental_code ?? '',
                'fuel_gas_consumption' => $this->numberForInput($record->fuel_gas_consumption, 4),
                'spare_part_availability' => $this->numberForInput($record->spare_part_availability),
                'comp_a_unplanned_shutdown_hours' => $this->numberForInput($record->comp_a_unplanned_shutdown_hours),
                'comp_a_shutdown_hours' => $this->numberForInput($record->comp_a_shutdown_hours),
                'comp_a_running_hours' => $this->numberForInput($record->comp_a_running_hours),
                'comp_b_unplanned_shutdown_hours' => $this->numberForInput($record->comp_b_unplanned_shutdown_hours),
                'comp_b_shutdown_hours' => $this->numberForInput($record->comp_b_shutdown_hours),
                'comp_b_running_hours' => $this->numberForInput($record->comp_b_running_hours),
                'comp_c_unplanned_shutdown_hours' => $this->numberForInput($record->comp_c_unplanned_shutdown_hours),
                'comp_c_shutdown_hours' => $this->numberForInput($record->comp_c_shutdown_hours),
                'comp_c_running_hours' => $this->numberForInput($record->comp_c_running_hours),
                'total_required_hours' => $this->numberForInput($record->total_required_hours),
                'availability_system_capacity' => $this->numberForInput($record->availability_system_capacity),
                'lpo' => $this->numberForInput($record->lpo),
                'reliability_percent' => $this->numberForInput($record->reliability_percent),
                'availability_percent' => $this->numberForInput($record->availability_percent),
                'doe_percent' => $this->numberForInput($record->doe_percent),
                'remark' => $record->remark ?? '',
                'pk100_shutdown_hours' => $this->numberForInput($record->pk100_shutdown_hours),
                'ltrx_a_running_hours' => $this->numberForInput($record->ltrx_a_running_hours),
                'ltrx_b_running_hours' => $this->numberForInput($record->ltrx_b_running_hours),
                'pk101_running_hours' => $this->numberForInput($record->pk101_running_hours),
            ])
            ->values()
            ->all();
    }

    private function latestPeriodStart(): string
    {
        $periodStart = AvailabilityLtro1bRecord::query()
            ->select('period_start')
            ->distinct()
            ->orderByDesc('period_start')
            ->value('period_start');

        return $periodStart ? Carbon::parse($periodStart)->format('Y-m-d') : '';
    }

    private function renumberPeriodRows(string $periodStart): void
    {
        AvailabilityLtro1bRecord::query()
            ->whereDate('period_start', $periodStart)
            ->orderBy('report_date')
            ->get()
            ->each(function (AvailabilityLtro1bRecord $record, int $index): void {
                $record->update(['row_no' => $index + 1]);
            });
    }

    private function summary(): array
    {
        $records = collect($this->rows);
        $sum = fn (string $field) => $records->sum(fn ($row) => (float) ($row[$field] ?: 0));
        $avg = function (string $field) use ($records): float {
            $values = $records
                ->map(fn ($row) => $row[$field] ?? null)
                ->filter(fn ($value) => $value !== null && $value !== '');

            return $values->isEmpty() ? 0 : (float) $values->avg(fn ($value) => (float) $value);
        };

        return [
            'days' => $records->count(),
            'total_required_hours' => $sum('total_required_hours'),
            'total_shutdown_hours' => $sum('comp_a_shutdown_hours') + $sum('comp_b_shutdown_hours') + $sum('comp_c_shutdown_hours'),
            'total_unplanned_hours' => $sum('comp_a_unplanned_shutdown_hours') + $sum('comp_b_unplanned_shutdown_hours') + $sum('comp_c_unplanned_shutdown_hours'),
            'avg_availability' => $avg('availability_percent'),
            'avg_reliability' => $avg('reliability_percent'),
            'avg_doe' => $avg('doe_percent'),
        ];
    }

    private function nullableNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) str_replace(',', '', (string) $value);
    }

    private function numberForInput($value, int $precision = 2): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, $precision, '.', '');
    }
}
