<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardMeetingSummaryRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_does_not_render_meeting_summary_card(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/panel')
            ->assertOk()
            ->assertDontSee('Ringkasan ruangan dan jadwal hari ini.')
            ->assertDontSee('ruangan tersedia')
            ->assertDontSee('Booking Saya');
    }
}
