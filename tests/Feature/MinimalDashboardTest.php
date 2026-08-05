<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinimalDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_uses_the_compact_meeting_and_reminder_layout(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/panel')
            ->assertOk()
            ->assertSeeText('Ringkasan kerja hari ini')
            ->assertSeeText('ruangan tersedia')
            ->assertSeeText('Reminder')
            ->assertSeeText('Hari Ini')
            ->assertSeeText('Akan Datang')
            ->assertSeeText('Terlambat')
            ->assertDontSeeText('Meeting Rooms Today')
            ->assertDontSeeText('Upcoming Reminders');
    }
}
