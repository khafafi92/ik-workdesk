<?php

namespace Tests\Feature;

use App\Mail\ReminderAlertMail;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Reminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderEmailAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_sends_selected_h3_alert_once_for_a_pending_reminder(): void
    {
        Carbon::setTestNow('2026-08-03 08:00:00');
        Mail::fake();

        $reminder = $this->createReminder([
            'reminder_at' => now()->addDays(3)->setTime(16, 0),
            'email_alarm_days' => [3, 1],
        ]);

        $this->artisan('reminders:send-email-alerts')->assertSuccessful();
        $this->artisan('reminders:send-email-alerts')->assertSuccessful();

        Mail::assertSent(ReminderAlertMail::class, 1);
        Mail::assertSent(ReminderAlertMail::class, function (ReminderAlertMail $mail) use ($reminder) {
            return $mail->reminder->is($reminder)
                && $mail->daysBefore === 3
                && $mail->hasTo('staff@kpmog.com');
        });

        $this->assertDatabaseHas('reminder_email_deliveries', [
            'reminder_id' => $reminder->id,
            'days_before' => 3,
        ]);

        $html = (new ReminderAlertMail($reminder, 3, 'Staff Test'))->render();
        $this->assertStringContainsString('Pengingat H-3', $html);
        $this->assertStringContainsString('Lihat Reminder', $html);
    }

    public function test_it_sends_h1_only_when_that_alarm_is_selected(): void
    {
        Carbon::setTestNow('2026-08-03 08:00:00');
        Mail::fake();

        $this->createReminder([
            'reminder_at' => now()->addDay()->setTime(16, 0),
            'email_alarm_days' => ['1'],
        ]);

        $this->createReminder([
            'title' => 'Alarm dimatikan',
            'reminder_at' => now()->addDay()->setTime(17, 0),
            'email_alarm_days' => [],
        ]);

        $this->artisan('reminders:send-email-alerts')->assertSuccessful();

        Mail::assertSent(ReminderAlertMail::class, function (ReminderAlertMail $mail) {
            return $mail->daysBefore === 1
                && $mail->reminder->title === 'Laporan bulanan';
        });
        Mail::assertSent(ReminderAlertMail::class, 1);
    }

    public function test_it_catches_up_h3_when_a_reminder_is_created_inside_the_h3_window(): void
    {
        Carbon::setTestNow('2026-08-05 10:00:00');
        Mail::fake();

        $reminder = $this->createReminder([
            'reminder_at' => now()->addDays(2)->setTime(10, 0),
            'email_alarm_days' => [3],
        ]);

        $this->artisan('reminders:send-email-alerts')->assertSuccessful();

        Mail::assertSent(ReminderAlertMail::class, function (ReminderAlertMail $mail) use ($reminder) {
            return $mail->reminder->is($reminder)
                && $mail->daysBefore === 3;
        });
        Mail::assertSent(ReminderAlertMail::class, 1);
    }

    public function test_it_sends_only_h1_when_h3_and_h1_are_both_due(): void
    {
        Carbon::setTestNow('2026-08-05 10:00:00');
        Mail::fake();

        $reminder = $this->createReminder([
            'reminder_at' => now()->addDay()->setTime(10, 0),
            'email_alarm_days' => [3, 1],
        ]);

        $this->artisan('reminders:send-email-alerts')->assertSuccessful();

        Mail::assertSent(ReminderAlertMail::class, function (ReminderAlertMail $mail) use ($reminder) {
            return $mail->reminder->is($reminder)
                && $mail->daysBefore === 1;
        });
        Mail::assertSent(ReminderAlertMail::class, 1);
    }

    public function test_it_does_not_send_for_a_completed_reminder(): void
    {
        Carbon::setTestNow('2026-08-03 08:00:00');
        Mail::fake();

        $this->createReminder([
            'status' => 'done',
            'reminder_at' => now()->addDays(3),
            'email_alarm_days' => [3, 1],
        ]);

        $this->artisan('reminders:send-email-alerts')->assertSuccessful();

        Mail::assertNothingSent();
    }

    private function createReminder(array $attributes = []): Reminder
    {
        $department = Department::query()->create([
            'code' => 'IT'.uniqid(),
            'name' => 'Information Technology',
            'is_active' => true,
        ]);

        $employee = Employee::query()->create([
            'department_id' => $department->id,
            'name' => 'Staff Test',
            'email' => 'staff@kpmog.com',
            'is_active' => true,
        ]);

        return Reminder::query()->create(array_merge([
            'employee_id' => $employee->id,
            'department_id' => $department->id,
            'reminder_type' => 'report',
            'title' => 'Laporan bulanan',
            'description' => 'Siapkan laporan sebelum deadline.',
            'reminder_at' => now()->addDays(3),
            'status' => 'pending',
            'email_alarm_days' => [3, 1],
        ], $attributes));
    }
}
