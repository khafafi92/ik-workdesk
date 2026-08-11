<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinimalDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_uses_the_compact_reminder_layout_without_meeting_summary(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/panel')
            ->assertOk()
            ->assertSeeText('Ringkasan kerja hari ini')
            ->assertSeeText('Reminder')
            ->assertSeeText('Hari Ini')
            ->assertSeeText('Akan Datang')
            ->assertSeeText('Terlambat')
            ->assertDontSeeText('ruangan tersedia')
            ->assertDontSeeText('Booking Saya')
            ->assertDontSeeText('Meeting Rooms Today')
            ->assertDontSeeText('Upcoming Reminders');
    }

    public function test_the_superadmin_dashboard_matches_the_compact_overview_layout(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeText('Service Desk')
            ->assertSeeText('Work Logs')
            ->assertSeeText('ruangan tersedia')
            ->assertSeeText('Reminder')
            ->assertSeeText('Hari Ini')
            ->assertSeeText('Akan Datang')
            ->assertSeeText('Terlambat')
            ->assertDontSeeText("Today's Reminders")
            ->assertDontSeeText('Upcoming Reminders');
    }
}
