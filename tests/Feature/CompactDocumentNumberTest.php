<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\WorkTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CompactDocumentNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_and_task_numbers_use_compact_daily_format(): void
    {
        Carbon::setTestNow('2026-08-12 10:00:00');

        $this->assertSame('R-12/08/26-0001', Ticket::generateRequestNo());
        $this->assertSame('R-12/08/26-0002', Ticket::generateRequestNo());
        $this->assertSame('T-12/08/26-0001', WorkTask::generateTaskNo());
        $this->assertSame('T-12/08/26-0002', WorkTask::generateTaskNo());

        Carbon::setTestNow('2026-08-13 10:00:00');

        $this->assertSame('R-13/08/26-0001', Ticket::generateRequestNo());
        $this->assertSame('T-13/08/26-0001', WorkTask::generateTaskNo());
    }
}
