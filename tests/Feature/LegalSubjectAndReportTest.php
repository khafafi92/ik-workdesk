<?php

namespace Tests\Feature;

use App\Filament\Resources\Reports\DepartmentActivityReportResource;
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

    public function test_department_report_counts_service_desk_assignments_without_work_logs(): void
    {
        $requester = Department::create(['code' => 'REQ', 'name' => 'Requester', 'is_active' => true]);
        $handler = Department::create(['code' => 'HANDLER', 'name' => 'Handler', 'is_active' => true]);
        $reviewer = Department::create(['code' => 'REVIEW', 'name' => 'Reviewer', 'is_active' => true]);
        $createdAt = Carbon::parse('2026-11-01 08:00:00');
        $open = Ticket::create(['ticket_no' => 'R-DEPT-01', 'subject' => 'Open', 'status' => 'open',
            'requester_department_id' => $requester->id, 'handler_department_id' => $handler->id]);
        $open->assignments()->create(['department_id' => $handler->id]);
        $done = Ticket::create(['ticket_no' => 'R-DEPT-02', 'subject' => 'Resolved', 'status' => 'resolved',
            'requester_department_id' => $requester->id, 'handler_department_id' => $handler->id,
            'resolved_at' => $createdAt->copy()->addMinutes(90)]);
        $done->forceFill(['created_at' => $createdAt])->save();
        $done->assignments()->create(['department_id' => $reviewer->id]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $reports = DepartmentActivityReportResource::getEloquentQuery()->get()->keyBy('id');
        $this->assertSame(2, (int) $reports[$requester->id]->total_requests);
        $this->assertSame(2, (int) $reports[$handler->id]->assigned_tickets);
        $this->assertSame(1, (int) $reports[$handler->id]->outstanding_tickets);
        $this->assertSame(1, (int) $reports[$handler->id]->completed_tickets);
        $this->assertSame(0, (int) $reports[$handler->id]->total_work_logs);
        $this->assertSame(90, (int) $reports[$handler->id]->average_resolution_minutes);
        $this->assertSame(1, (int) $reports[$reviewer->id]->assigned_tickets);
        $this->assertSame(1, (int) $reports[$reviewer->id]->completed_tickets);
    }
}
