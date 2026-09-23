<?php

namespace Tests\Audit;

use App\Filament\Pages\Dashboard;
use App\Models\Reminder;
use App\Models\Ticket;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/** These probes document current behavior, not acceptance criteria for fixes. */
class DashboardAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Audit requires SQLite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->travelTo(now()->setDate(2026, 9, 21)->setTime(10, 0));
    }

    public function test_reminder_counts_are_capped_at_five_and_only_three_items_render(): void
    {
        foreach (['today' => now()->setTime(11, 0), 'upcoming' => now()->addDay(), 'overdue' => now()->subDay()] as $group => $date) {
            for ($i = 1; $i <= 8; $i++) {
                Reminder::create(['title' => "AUDIT-{$group}-{$i}", 'reminder_type' => 'report', 'reminder_at' => $date->copy()->addMinutes($i), 'status' => 'pending']);
            }
        }

        $data = app(Dashboard::class)->getDashboardData();
        $this->assertDatabaseCount('reminders', 24);
        foreach (['todayReminders', 'upcomingReminders', 'overdueReminders'] as $key) {
            $this->assertCount(5, $data[$key]);
        }
        $response = $this->get('/panel')->assertOk();
        foreach (['today', 'upcoming', 'overdue'] as $group) {
            $response->assertSeeText("AUDIT-{$group}-3")->assertDontSeeText("AUDIT-{$group}-4");
        }
        file_put_contents(__DIR__.'/reminder-evidence.json', json_encode([
            'fixture_only' => true,
            'actual_per_group' => 8,
            'badge_per_group' => 5,
            'rendered_items_per_group' => 3,
        ], JSON_PRETTY_PRINT));
    }

    public function test_today_heading_includes_historical_tickets_without_detail_links(): void
    {
        $ticket = Ticket::create([
            'ticket_no' => 'AUDIT-OLD-001',
            'subject' => 'Permintaan uji dengan judul panjang yang perlu dibaca lengkap',
            'status' => 'resolved',
            'reported_at' => now()->subMonth(),
        ]);
        $ticket->forceFill(['created_at' => now()->subMonth()])->saveQuietly();
        $data = app(Dashboard::class)->getDashboardData();
        $this->assertSame(1, $data['ticketStats'][0]['value']);
        $response = $this->get('/panel')->assertOk()->assertSeeText('Ringkasan kerja hari ini')->assertSeeText('AUDIT-OLD-001');
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(0, $xpath->query('//tr[td[contains(., "AUDIT-OLD-001")]]//a')->length);
        $this->assertStringNotContainsString($ticket->subject, $xpath->query('//tr[td[contains(., "AUDIT-OLD-001")]]')->item(0)->textContent);
    }

    public function test_empty_dashboard_and_its_three_destinations_render(): void
    {
        $this->get('/panel')->assertOk()
            ->assertSeeText('Tidak ada reminder hari ini.')
            ->assertSeeText('Belum ada jadwal berikutnya.')
            ->assertSeeText('Tidak ada reminder terlambat.')
            ->assertSeeText('Belum ada Service Desk.')
            ->assertSeeText('Belum ada Work Log.');
        $data = app(Dashboard::class)->getDashboardData();
        foreach (['ticketsUrl', 'workTasksUrl', 'remindersUrl'] as $key) {
            $this->get($data[$key])->assertOk();
        }
    }
}
