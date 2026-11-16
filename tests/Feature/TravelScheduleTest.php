<?php

namespace Tests\Feature;

use App\Services\Travel\TravelPresentation;
use App\Services\Travel\TravelSchedule;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TravelScheduleTest extends TestCase
{
    public function test_tba_uses_dates_without_inventing_midnight_and_counts_days_inclusively(): void
    {
        $schedule = app(TravelSchedule::class)->normalize(['depart_date' => '2026-08-13', 'return_date' => '2026-09-01',
            'depart_time_tba' => true, 'return_time_tba' => true, 'depart_time' => '08:00']);
        $this->assertSame('2026-08-13', $schedule['depart_at']);
        $this->assertSame('2026-09-01', $schedule['return_at']);
        $this->assertSame(20, $schedule['days']);
        $this->assertSame(19, $schedule['nights']);
        $this->assertTrue($schedule['depart_time_tba']);
        $this->assertSame('13/08/2026 TBA', TravelPresentation::date($schedule['depart_at'], true));
    }

    public function test_known_mixed_and_legacy_times_round_trip(): void
    {
        $service = app(TravelSchedule::class);
        foreach ([['2026-08-13T00:00', '2026-09-01T18:30'], ['2026-08-13', '2026-09-01T18:30'],
            ['2026-08-13T08:00', '2026-09-01'], ['2026-08-13', '2026-08-13']] as [$departure, $return]) {
            $legacy = $service->normalize(['depart_at' => $departure, 'return_at' => $return]);
            $this->assertSame($departure, $legacy['depart_at']);
            $this->assertSame($return, $legacy['return_at']);
            $this->assertSame($legacy, $service->normalize($service->formData($legacy)));
        }
        $this->assertSame('13/08/2026 00:00', TravelPresentation::date('2026-08-13T00:00', true));
    }

    public function test_missing_invalid_or_reversed_dates_and_known_times_are_rejected(): void
    {
        $base = ['depart_date' => '2026-08-13', 'return_date' => '2026-08-13', 'depart_time_tba' => false,
            'return_time_tba' => false, 'depart_time' => '08:00', 'return_time' => '09:00'];
        foreach ([['depart_date' => ''], ['depart_date' => '2026-02-30'], ['return_date' => '2026-08-12'],
            ['depart_time' => ''], ['return_time' => '25:00'], ['return_time' => '07:00'], ['depart_time_tba' => 'TBA']] as $changes) {
            try {
                app(TravelSchedule::class)->normalize([...$base, ...$changes]);
                $this->fail('Invalid schedule accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_duration_preview_depends_only_on_valid_dates_and_never_trusts_supplied_totals(): void
    {
        $service = app(TravelSchedule::class);
        $this->assertSame(['days' => 20, 'nights' => 19], $service->duration(['depart_date' => '2026-08-13',
            'return_date' => '2026-09-01', 'depart_time_tba' => false, 'depart_time' => '', 'days' => 999, 'nights' => 998]));
        $this->assertSame(['days' => 1, 'nights' => 0], $service->duration(['depart_at' => '2026-08-13T08:00', 'return_at' => '2026-08-13T17:00']));
        foreach ([['depart_date' => '', 'return_date' => '2026-09-01'],
            ['depart_date' => '2026-02-30', 'return_date' => '2026-09-01'],
            ['depart_date' => '2026-08-13garbage', 'return_date' => '2026-09-01'],
            ['depart_date' => '2026-08-13', 'return_date' => '2026-08-12']] as $invalid) {
            try {
                $service->duration($invalid);
                $this->fail('Invalid dates displayed a duration.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }
}
