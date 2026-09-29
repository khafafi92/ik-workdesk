<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\MttrRecord;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

/**
 * Komponen utama halaman /monitoring.
 *
 * Alur besarnya:
 * 1. View: resources/views/livewire/mttr-form.blade.php menampilkan form input dan dashboard.
 * 2. Method save(): menerima input user lalu menyimpan record ke tabel mttr_records.
 * 3. Method render(): membaca ulang mttr_records untuk kartu summary, last event, dan grafik donut.
 */
class MttrForm extends Component
{
    // Master data untuk dropdown Unit dan Category pada form input MTTR.
    public $units;

    public $categories;

    // Property ini terhubung langsung dengan input di view memakai wire:model.
    public $sequence_no = '';

    public $report_month = '';

    public $unit_id = '';

    public $category_id = '';

    public $rental_period = '';

    public $shutdown_date = '';

    public $shutdown_time = '';

    public $running_date = '';

    public $running_time = '';

    public $downtime_minutes = 0;

    public $running_hours = '';

    public $pk_100 = '';

    public $indication = '';

    public $immediate_cause = '';

    public $activity_troubleshooting = '';

    public function mount(): void
    {
        // Query master data aktif saat halaman monitoring pertama kali dibuka.
        $this->units = Unit::where('is_active', true)->orderBy('unit_name')->get();
        $this->categories = Category::where('is_active', true)->orderBy('category_name')->get();
    }

    public function updated(): void
    {
        // Setiap input berubah, downtime dihitung ulang otomatis dari shutdown sampai running.
        $this->calculateDowntime();
    }

    public function save(): void
    {
        $this->calculateDowntime();
        $rhPk100Enabled = $this->rhPk100Enabled();

        // Validasi ini memastikan data yang masuk ke tabel mttr_records sudah lengkap dan formatnya benar.
        $rules = [
            'sequence_no' => 'required|integer|min:1',
            'unit_id' => 'required|exists:units,id',
            'category_id' => 'required|exists:categories,id',
            'report_month' => 'required|date_format:Y-m',
            'shutdown_date' => 'required|date',
            'shutdown_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'running_date' => 'required|date',
            'running_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'downtime_minutes' => 'required|integer|min:0',
            'indication' => 'required|string|max:1000',
        ];

        if ($rhPk100Enabled) {
            $rules['running_hours'] = 'nullable|numeric|min:0';
            $rules['pk_100'] = 'nullable|numeric|min:0';
        }

        $this->validate($rules);

        $category = Category::find($this->category_id);
        $shutdownDatetime = $this->buildDateTime($this->shutdown_date, $this->shutdown_time);
        $runningDatetime = $this->buildDateTime($this->running_date, $this->running_time);

        // Guard: waktu running tidak boleh lebih awal dari waktu shutdown.
        if ($runningDatetime->lessThan($shutdownDatetime)) {
            $this->addError('running_date', 'Date Running tidak boleh lebih awal dari Date Shutdown.');

            return;
        }

        // INSERT ke database: field di bawah akan tersimpan sebagai satu baris baru di tabel mttr_records.
        $values = [
            'sequence_no' => $this->sequence_no ?: null,
            'unit_id' => $this->unit_id,
            'category_id' => $this->category_id,
            'rental_period' => $this->rental_period,
            'shutdown_month' => Carbon::createFromFormat('Y-m', $this->report_month)->startOfMonth()->toDateString(),
            'shutdown_datetime' => $shutdownDatetime,
            'running_datetime' => $runningDatetime,
            'downtime_minutes' => $this->downtime_minutes,
            'category' => $category?->category_name,
            'indication' => $this->indication,
            'immediate_cause' => $this->immediate_cause,
            'activity_troubleshooting' => $this->activity_troubleshooting,
        ];

        if ($rhPk100Enabled) {
            $values['running_hours'] = $this->nullableNumber($this->running_hours);

            if (Schema::hasColumn('mttr_records', 'pk_100')) {
                $values['pk_100'] = $this->nullableNumber($this->pk_100);
            }
        }

        MttrRecord::create($values);

        // Kosongkan form setelah data berhasil tersimpan agar user siap input data berikutnya.
        $this->reset([
            'sequence_no',
            'report_month',
            'unit_id',
            'category_id',
            'rental_period',
            'shutdown_date',
            'shutdown_time',
            'running_date',
            'running_time',
            'downtime_minutes',
            'running_hours',
            'pk_100',
            'indication',
            'immediate_cause',
            'activity_troubleshooting',
        ]);

        session()->flash('success', 'Data MTTR berhasil disubmit.');
    }

    public function render()
    {
        // Data terakhir yang ditampilkan di tabel "Last Event" pada halaman monitoring.
        $recentRecords = MttrRecord::query()
            ->with(['unit', 'categoryMaster'])
            ->orderByDesc('shutdown_datetime')
            ->orderByDesc('sequence_no')
            ->limit(2)
            ->get()
            ->sortBy([
                ['shutdown_datetime', 'asc'],
                ['sequence_no', 'asc'],
            ])
            ->values();

        // Query angka ringkasan: total event, total downtime, dan downtime khusus kategori Unplan.
        $totalRecords = MttrRecord::count();
        $totalDowntime = (int) MttrRecord::sum('downtime_minutes');
        $unplannedDowntime = (int) MttrRecord::query()
            ->leftJoin('categories', 'categories.id', '=', 'mttr_records.category_id')
            ->where(function ($query) {
                $query->where('categories.category_name', 'like', 'Unplan%')
                    ->orWhere('mttr_records.category', 'like', 'Unplan%');
            })
            ->sum('mttr_records.downtime_minutes');

        // Query ranking penyebab/indikasi/activity untuk membantu melihat masalah paling dominan.
        $topCauses = DB::table('mttr_records')
            ->selectRaw("COALESCE(NULLIF(immediate_cause, ''), 'Belum diisi') as cause_name")
            ->selectRaw('COUNT(*) as event_count')
            ->selectRaw('SUM(downtime_minutes) as downtime_minutes')
            ->groupBy('cause_name')
            ->orderByDesc('downtime_minutes')
            ->limit(5)
            ->get();

        $topIndications = DB::table('mttr_records')
            ->selectRaw("COALESCE(NULLIF(indication, ''), 'Belum diisi') as indication_name")
            ->selectRaw('COUNT(*) as event_count')
            ->selectRaw('SUM(downtime_minutes) as downtime_minutes')
            ->groupBy('indication_name')
            ->orderByDesc('downtime_minutes')
            ->limit(5)
            ->get();

        $topActivities = DB::table('mttr_records')
            ->selectRaw("COALESCE(NULLIF(activity_troubleshooting, ''), 'Belum diisi') as activity_name")
            ->selectRaw('COUNT(*) as event_count')
            ->selectRaw('SUM(downtime_minutes) as downtime_minutes')
            ->groupBy('activity_name')
            ->orderByDesc('downtime_minutes')
            ->limit(5)
            ->get();

        // Summary per unit dipakai untuk donut downtime dan jumlah shutdown per unit.
        $unitColors = [
            'Unit-A' => '#FACC15',
            'Unit-B' => '#06A9D8',
            'Unit-C' => '#93D34B',
        ];

        $unitDashboard = Unit::query()
            ->where('is_active', true)
            ->orderBy('unit_name')
            ->get()
            ->map(function (Unit $unit) use ($unitColors) {
                $unplanDowntime = MttrRecord::query()
                    ->where('unit_id', $unit->id)
                    ->where(function ($query) {
                        $query->where('category', 'Unplan')
                            ->orWhereHas('categoryMaster', fn ($categoryQuery) => $categoryQuery->where('category_name', 'Unplan'));
                    })
                    ->sum('downtime_minutes');

                return [
                    'name' => $unit->unit_name,
                    'color' => $unitColors[$unit->unit_name] ?? '#64748B',
                    'unplan_downtime' => (int) $unplanDowntime,
                    'shutdown_count' => MttrRecord::where('unit_id', $unit->id)->count(),
                ];
            });

        $categoryOrder = ['Plan', 'Unplan', 'Plan MEPG', 'Unplan MEPG'];
        $categoryColors = [
            'Plan' => '#2563EB',
            'Unplan' => '#DC2626',
            'Plan MEPG' => '#F59E0B',
            'Unplan MEPG' => '#7C3AED',
        ];

        // Summary per category dipakai untuk donut total shutdown by category.
        $categoryDashboard = collect($categoryOrder)->map(function (string $categoryName) use ($categoryColors) {
            return [
                'name' => $categoryName,
                'color' => $categoryColors[$categoryName],
                'shutdown_count' => MttrRecord::query()
                    ->where(function ($query) use ($categoryName) {
                        $query->where('category', $categoryName)
                            ->orWhereHas('categoryMaster', fn ($categoryQuery) => $categoryQuery->where('category_name', $categoryName));
                    })
                    ->count(),
            ];
        });

        // String conic-gradient ini dikirim ke view untuk menggambar donut CSS.
        $unitDowntimeGradient = $this->buildConicGradient($unitDashboard->map(fn ($unit) => [
            'value' => $unit['unplan_downtime'],
            'color' => $unit['color'],
        ])->all());

        $unitShutdownGradient = $this->buildConicGradient($unitDashboard->map(fn ($unit) => [
            'value' => $unit['shutdown_count'],
            'color' => $unit['color'],
        ])->all());

        $categoryShutdownGradient = $this->buildConicGradient($categoryDashboard->map(fn ($category) => [
            'value' => $category['shutdown_count'],
            'color' => $category['color'],
        ])->all());
        $rhPk100Enabled = $this->rhPk100Enabled();
        $pk100AvailabilityChart = $rhPk100Enabled ? $this->buildPk100AvailabilityChart() : [];

        return view('livewire.mttr-form', compact(
            'recentRecords',
            'totalRecords',
            'totalDowntime',
            'unplannedDowntime',
            'topCauses',
            'topIndications',
            'topActivities',
            'unitDashboard',
            'categoryDashboard',
            'unitDowntimeGradient',
            'unitShutdownGradient',
            'categoryShutdownGradient',
            'rhPk100Enabled',
            'pk100AvailabilityChart',
        ))->layout('components.layouts.app');
    }

    private function calculateDowntime(): void
    {
        if (! $this->shutdown_date || ! $this->shutdown_time || ! $this->running_date || ! $this->running_time) {
            return;
        }

        // Downtime = selisih menit antara Date/Time Shutdown dan Date/Time Running.
        $start = $this->buildDateTime($this->shutdown_date, $this->shutdown_time);
        $end = $this->buildDateTime($this->running_date, $this->running_time);

        $this->downtime_minutes = $end->greaterThanOrEqualTo($start)
            ? (int) round($start->diffInMinutes($end))
            : 0;
    }

    private function buildDateTime(string $date, string $time): Carbon
    {
        // Gabungkan input tanggal dan jam menjadi object Carbon agar mudah dibandingkan/dihitung.
        return Carbon::parse($date.' '.$time);
    }

    private function nullableNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function rhPk100Enabled(): bool
    {
        return (bool) config('features.mttr_rh_pk100_enabled', false);
    }

    private function buildPk100AvailabilityChart(): array
    {
        $hasPk100Column = Schema::hasColumn('mttr_records', 'pk_100');
        $records = MttrRecord::query()
            ->with('unit')
            ->whereNotNull('shutdown_month')
            ->get([
                'unit_id',
                'shutdown_month',
                'shutdown_datetime',
                'running_datetime',
                'immediate_cause',
                'downtime_minutes',
                ...($hasPk100Column ? ['pk_100'] : []),
            ]);

        $months = $records
            ->map(fn (MttrRecord $record) => Carbon::parse($record->shutdown_month)->format('Y-m'))
            ->unique()
            ->sort()
            ->values();

        $unitNames = Unit::query()
            ->where('is_active', true)
            ->orderBy('unit_name')
            ->pluck('unit_name');

        $unitDowntimeByMonth = $records
            ->groupBy(fn (MttrRecord $record) => Carbon::parse($record->shutdown_month)->format('Y-m'))
            ->map(function ($monthRecords) {
                return $monthRecords
                    ->groupBy(fn (MttrRecord $record) => $record->unit?->unit_name ?? 'Unknown')
                    ->map(fn ($unitRecords) => (float) $unitRecords->sum('downtime_minutes'));
            });

        $pk100EventDowntimeByMonth = $records
            ->filter(fn (MttrRecord $record) => $hasPk100Column && $record->pk_100 !== null && $record->pk_100 > 0)
            ->groupBy(function (MttrRecord $record) {
                $month = Carbon::parse($record->shutdown_month)->format('Y-m');
                $shutdown = $record->shutdown_datetime
                    ? Carbon::parse($record->shutdown_datetime)->format('Y-m-d H:i:s')
                    : 'no-shutdown';
                $running = $record->running_datetime
                    ? Carbon::parse($record->running_datetime)->format('Y-m-d H:i:s')
                    : 'no-running';
                $cause = $record->immediate_cause ?: 'no-cause';

                return "{$month}|{$shutdown}|{$running}|{$cause}";
            })
            ->map(function ($eventRecords) {
                return [
                    'month' => Carbon::parse($eventRecords->first()->shutdown_month)->format('Y-m'),
                    'downtime_minutes' => (float) $eventRecords->max('pk_100'),
                ];
            })
            ->groupBy('month')
            ->map(fn ($events) => (float) $events->sum('downtime_minutes'));

        $availabilityPercent = function (string $month, float $downtimeMinutes): float {
            $capacityMinutes = Carbon::createFromFormat('Y-m', $month)->daysInMonth * 24 * 60;

            if ($capacityMinutes <= 0) {
                return 0;
            }

            return round(max((($capacityMinutes - $downtimeMinutes) / $capacityMinutes) * 100, 0), 2);
        };

        $title = $months->isNotEmpty()
            ? 'Availability (%), '
                .Carbon::createFromFormat('Y-m', $months->first())->format('M Y')
                .' - '
                .Carbon::createFromFormat('Y-m', $months->last())->format('M Y')
            : 'Availability (%)';

        return [
            'title' => $title,
            'labels' => $months
                ->map(fn ($month) => Carbon::createFromFormat('Y-m', $month)->format('M-y'))
                ->values(),
            'unit_series' => $unitNames
                ->map(function (string $unitName) use ($months, $unitDowntimeByMonth, $availabilityPercent) {
                    return [
                        'label' => $unitName,
                        'values' => $months
                            ->map(function ($month) use ($unitName, $unitDowntimeByMonth, $availabilityPercent) {
                                $downtimeMinutes = (float) ($unitDowntimeByMonth[$month][$unitName] ?? 0);

                                return $availabilityPercent($month, $downtimeMinutes);
                            })
                            ->values(),
                    ];
                })
                ->values(),
            'actual_kpi' => $months
                ->map(fn ($month) => $availabilityPercent($month, (float) ($pk100EventDowntimeByMonth[$month] ?? 0)))
                ->values(),
            'target_kpi' => $months
                ->map(fn () => 95)
                ->values(),
        ];
    }

    private function buildConicGradient(array $segments): string
    {
        // Membentuk potongan warna donut berdasarkan proporsi value terhadap total.
        $total = collect($segments)->sum('value');

        if ($total <= 0) {
            return '#E5E7EB 0deg 360deg';
        }

        $start = 0;
        $parts = [];

        foreach ($segments as $segment) {
            if ($segment['value'] <= 0) {
                continue;
            }

            $end = $start + (($segment['value'] / $total) * 360);
            $parts[] = "{$segment['color']} {$start}deg {$end}deg";
            $start = $end;
        }

        if ($start < 360) {
            $parts[] = "#E5E7EB {$start}deg 360deg";
        }

        return implode(', ', $parts);
    }
}
