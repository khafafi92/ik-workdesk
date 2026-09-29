<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\LegalSubjectCategory;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkTask;
use App\Services\Reports\ReportQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LegalSubjectAndReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_ticket_uses_the_selected_active_subject_category(): void
    {
        $legal = Department::query()->create([
            'code' => 'LEGAL',
            'name' => 'Legal',
            'is_active' => true,
        ]);
        $subjectCategory = LegalSubjectCategory::query()->create([
            'name' => 'Perjanjian Kerja Sama',
            'is_active' => true,
        ]);

        $ticket = Ticket::query()->create([
            'ticket_no' => 'R-LEGAL-001',
            'handler_department_id' => $legal->id,
            'legal_subject_category_id' => $subjectCategory->id,
            'subject' => 'Nilai sementara yang tidak boleh tersimpan',
            'legal_document_types' => [
                'articles_of_association_related_party',
            ],
        ]);

        $this->assertSame('Perjanjian Kerja Sama', $ticket->fresh()->subject);
        $this->assertSame(
            ['articles_of_association_related_party'],
            $ticket->fresh()->legal_document_types
        );
    }

    public function test_report_sums_completed_work_log_duration(): void
    {
        $ticket = Ticket::query()->create([
            'ticket_no' => 'R-REPORT-001',
            'subject' => 'Perhitungan durasi kerja',
        ]);
        WorkTask::query()->create([
            'task_no' => 'T-REPORT-001',
            'ticket_id' => $ticket->id,
            'title' => 'Work log selesai',
            'start_at' => Carbon::parse('2026-09-29 08:00:00'),
            'completed_at' => Carbon::parse('2026-09-29 09:30:00'),
        ]);
        WorkTask::query()->create([
            'task_no' => 'T-REPORT-002',
            'ticket_id' => $ticket->id,
            'title' => 'Work log belum selesai',
            'start_at' => Carbon::parse('2026-09-29 10:00:00'),
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $reportedTicket = app(ReportQueryService::class)
            ->tickets($admin)
            ->findOrFail($ticket->id);

        $this->assertSame(90, (int) $reportedTicket->work_duration_minutes);
    }
}
