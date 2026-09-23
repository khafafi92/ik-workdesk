<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Reminders\Pages\ListReminders;
use App\Filament\Resources\Reminders\ReminderResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\WorkTasks\WorkTaskResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Reminder;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkTask;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class DashboardAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Dashboard regression tests require SQLite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->travelTo(now()->setDate(2026, 9, 22)->setTime(10, 0));
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    private function reminder(string $title, mixed $date, ?Employee $employee = null, string $status = 'pending'): Reminder
    {
        return Reminder::create([
            'title' => $title,
            'reminder_type' => 'report',
            'reminder_at' => $date,
            'status' => $status,
            'employee_id' => $employee?->id,
            'department_id' => $employee?->department_id,
        ]);
    }

    private function employee(User $user, string $code): Employee
    {
        $department = Department::create(['code' => $code, 'name' => $code, 'is_active' => true]);

        return Employee::create(['user_id' => $user->id, 'department_id' => $department->id, 'name' => $code, 'is_active' => true]);
    }

    public function test_reminder_totals_are_complete_while_previews_show_three_per_group(): void
    {
        $this->admin();
        foreach (['today' => now()->setTime(11, 0), 'upcoming' => now()->addDay(), 'overdue' => now()->subDay()] as $group => $date) {
            for ($i = 1; $i <= 8; $i++) {
                $this->reminder("{$group}-reminder-{$i}", $date->copy()->addMinutes($i));
            }
            $this->reminder("{$group}-completed", $date, status: 'done');
        }

        $data = app(Dashboard::class)->getDashboardData();
        $response = $this->get('/panel')->assertOk();
        foreach (['today', 'upcoming', 'overdue'] as $group) {
            $this->assertSame(8, $data['reminderCounts'][$group]);
            $this->assertCount(3, $data[$group.'Reminders']);
            $response->assertSeeText("{$group}-reminder-3")->assertDontSeeText("{$group}-reminder-4")
                ->assertDontSeeText("{$group}-completed");
        }
        $this->assertSame(3, substr_count($response->getContent(), 'Menampilkan 3 dari 8 reminder'));
    }

    public function test_schedule_links_match_counts_and_preserve_day_boundaries(): void
    {
        $this->admin();
        $beforeToday = $this->reminder('Before today', now()->startOfDay()->subSecond());
        $todayStart = $this->reminder('Midnight today', now()->startOfDay());
        $todayEnd = $this->reminder('End today', now()->endOfDay()->startOfSecond());
        $tomorrow = $this->reminder('Tomorrow midnight', now()->addDay()->startOfDay());
        $completed = $this->reminder('Already done', now(), status: 'done');
        $data = app(Dashboard::class)->getDashboardData();
        $this->assertSame(['today' => 2, 'upcoming' => 1, 'overdue' => 1], $data['reminderCounts']);

        foreach (['today' => [$todayStart, $todayEnd], 'upcoming' => [$tomorrow], 'overdue' => [$beforeToday]] as $schedule => $expected) {
            parse_str(parse_url($data['reminderUrls'][$schedule], PHP_URL_QUERY), $query);
            $this->assertSame($schedule, $query['filters']['schedule']['value']);
            Livewire::withQueryParams($query)->test(ListReminders::class)
                ->assertCanSeeTableRecords($expected)
                ->assertCountTableRecords(count($expected))
                ->assertCanNotSeeTableRecords([$completed]);
            $this->get($data['reminderUrls'][$schedule])->assertOk();
        }
    }

    public function test_reminder_totals_previews_and_filters_respect_employee_scope(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $employee = $this->employee($owner, 'OWN');
        $other = $this->employee(User::factory()->create(['is_admin' => false]), 'OTHER');
        $own = $this->reminder('Own reminder', now(), $employee);
        for ($i = 0; $i < 8; $i++) {
            $this->reminder('Private reminder '.$i, now(), $other);
        }
        $this->actingAs($owner);
        $data = app(Dashboard::class)->getDashboardData();
        $this->assertSame(1, $data['reminderCounts']['today']);
        $this->assertSame([$own->id], $data['todayReminders']->modelKeys());
        $this->get('/panel')->assertOk()->assertSeeText('Own reminder')->assertDontSeeText('Private reminder')
            ->assertSeeText('Menampilkan 1 dari 1 reminder')->assertDontSeeText('Seluruh periode');
        parse_str(parse_url($data['reminderUrls']['today'], PHP_URL_QUERY), $query);
        Livewire::withQueryParams($query)->test(ListReminders::class)->assertCanSeeTableRecords([$own])->assertCountTableRecords(1);
    }

    public function test_full_titles_have_working_detail_links_and_explicit_period_labels(): void
    {
        $this->admin();
        $ticket = Ticket::create([
            'ticket_no' => 'DASH-OLD-001',
            'subject' => 'Permintaan lama dengan judul lengkap melebihi tiga puluh empat karakter',
            'status' => 'open',
            'reported_at' => now()->subMonth(),
        ]);
        $ticket->forceFill(['created_at' => now()->subMonth()])->saveQuietly();
        $task = WorkTask::create(['task_no' => 'DASH-TASK-001', 'title' => 'Pekerjaan dengan judul panjang yang harus bisa dibaca secara lengkap', 'status' => 'planned']);
        $reminder = $this->reminder('Reminder panjang untuk pemeriksaan akses rincian pekerjaan', now());
        $response = $this->get('/panel')->assertOk()->assertSeeText('Ringkasan pekerjaan')
            ->assertSeeText('Seluruh periode')->assertDontSeeText('Ringkasan kerja hari ini')
            ->assertSeeText('Lihat semua tiket')->assertSeeText('Lihat semua pekerjaan');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        foreach ([
            [TicketResource::getUrl('view', ['record' => $ticket]), $ticket->subject],
            [WorkTaskResource::getUrl('view', ['record' => $task]), $task->title],
            [ReminderResource::getUrl('view', ['record' => $reminder]), $reminder->title],
        ] as [$url, $title]) {
            $link = $xpath->query('//a[@href="'.$url.'"]')->item(0);
            $this->assertNotNull($link, $url);
            $this->assertSame($title, trim($link->textContent));
            $this->get($url)->assertOk();
        }
        $this->assertSame(1, app(Dashboard::class)->getDashboardData()['ticketStats'][0]['value']);
    }

    public function test_detail_links_do_not_expose_other_departments_work(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $own = $this->employee($owner, 'OWN');
        $other = $this->employee(User::factory()->create(['is_admin' => false]), 'OTHER');
        foreach (['tickets.view', 'worklogs.view'] as $code) {
            $permission = Permission::create(['code' => $code, 'name' => $code, 'is_active' => true]);
            $owner->directPermissions()->attach($permission);
        }
        $ticket = Ticket::create(['ticket_no' => 'OWN-001', 'subject' => 'Own ticket', 'employee_id' => $own->id]);
        $privateTicket = Ticket::create(['ticket_no' => 'PRIVATE-001', 'subject' => 'Private ticket', 'employee_id' => $other->id]);
        $task = WorkTask::create(['task_no' => 'OWN-TASK-001', 'title' => 'Own task', 'ticket_id' => $ticket->id, 'department_id' => $own->department_id, 'status' => 'planned']);
        $privateTask = WorkTask::create(['task_no' => 'PRIVATE-TASK-001', 'title' => 'Private task', 'ticket_id' => $privateTicket->id, 'department_id' => $other->department_id, 'status' => 'planned']);
        $this->actingAs($owner);
        $page = app(Dashboard::class);
        $response = $this->get('/panel')->assertOk()->assertSeeText('Own ticket')->assertSeeText('Own task')
            ->assertDontSeeText('Private ticket')->assertDontSeeText('Private task');
        foreach ([$page->getTicketUrl($ticket), $page->getWorkTaskUrl($task)] as $url) {
            $response->assertSee($url, false);
            $this->get($url)->assertOk();
        }
        $this->assertNull($page->getTicketUrl($privateTicket));
        $this->assertNull($page->getWorkTaskUrl($privateTask));
        $this->get(TicketResource::getUrl('view', ['record' => $privateTicket]))->assertNotFound();
        $this->get(WorkTaskResource::getUrl('view', ['record' => $privateTask]))->assertNotFound();
    }

    public function test_empty_dashboard_retains_clear_messages_and_hides_unavailable_list_links(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->get('/panel')->assertOk()
            ->assertSeeText('Tidak ada reminder hari ini.')
            ->assertSeeText('Belum ada jadwal berikutnya.')
            ->assertSeeText('Tidak ada reminder terlambat.')
            ->assertSeeText('Belum ada Service Desk.')
            ->assertSeeText('Belum ada Work Log.')
            ->assertDontSeeText('Lihat semua tiket')->assertDontSeeText('Lihat semua pekerjaan')
            ->assertDontSeeText('Menampilkan 0 dari 0');
    }
}
