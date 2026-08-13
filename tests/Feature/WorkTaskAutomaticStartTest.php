<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\WorkTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkTaskAutomaticStartTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_desk_work_log_uses_request_reported_time_as_start(): void
    {
        $reportedAt = Carbon::parse('2026-08-12 09:15:00');
        $ticket = Ticket::query()->create([
            'ticket_no' => 'R 12/08/26-9001',
            'subject' => 'Automatic work log start',
            'reported_at' => $reportedAt,
        ]);

        $task = WorkTask::query()->create([
            'task_no' => 'T 12/08/26-9001',
            'ticket_id' => $ticket->id,
            'title' => 'Generated from service desk',
            'status' => 'planned',
        ]);

        $this->assertNotNull($task->start_at);
        $this->assertTrue($task->start_at->equalTo($reportedAt));
    }

    public function test_explicit_work_log_start_is_not_overwritten(): void
    {
        $ticket = Ticket::query()->create([
            'ticket_no' => 'R 12/08/26-9002',
            'subject' => 'Manual work log start',
            'reported_at' => '2026-08-12 09:15:00',
        ]);
        $manualStart = Carbon::parse('2026-08-13 10:30:00');

        $task = WorkTask::query()->create([
            'task_no' => 'T 12/08/26-9002',
            'ticket_id' => $ticket->id,
            'title' => 'Manually scheduled work',
            'status' => 'planned',
            'start_at' => $manualStart,
        ]);

        $this->assertTrue($task->start_at->equalTo($manualStart));
    }
}
