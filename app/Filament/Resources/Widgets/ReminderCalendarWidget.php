<?php

namespace App\Filament\Resources\Reminders\Widgets;

use App\Filament\Resources\Reminders\ReminderResource;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class ReminderCalendarWidget extends Widget
{
    protected string $view = 'filament.resources.reminders.widgets.reminder-calendar-widget';

    protected int | string | array $columnSpan = 'full';

    public function getCalendarData(): array
    {
        $currentMonth = now()->startOfMonth();
        $startOfCalendar = $currentMonth->copy()->startOfWeek();
        $endOfCalendar = $currentMonth->copy()->endOfMonth()->endOfWeek();

        $reminders = ReminderResource::getEloquentQuery()
            ->whereBetween('reminder_at', [
                $startOfCalendar->copy()->startOfDay(),
                $endOfCalendar->copy()->endOfDay(),
            ])
            ->orderBy('reminder_at')
            ->get()
            ->groupBy(fn ($reminder) => $reminder->reminder_at->format('Y-m-d'));

        $weeks = [];
        $day = $startOfCalendar->copy();

        while ($day->lte($endOfCalendar)) {
            $week = [];

            for ($i = 0; $i < 7; $i++) {
                $dateKey = $day->format('Y-m-d');

                $week[] = [
                    'date' => $day->copy(),
                    'isCurrentMonth' => $day->month === $currentMonth->month,
                    'isToday' => $day->isToday(),
                    'reminders' => $reminders->get($dateKey, collect()),
                ];

                $day->addDay();
            }

            $weeks[] = $week;
        }

        return [
            'title' => $currentMonth->format('F Y'),
            'weekDays' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'weeks' => $weeks,
        ];
    }

    public function formatReminderType(?string $type): string
    {
        return match ($type) {
            'service_request' => 'Service Desk',
            default => str($type ?? 'general')
                ->replace('_', ' ')
                ->title()
                ->toString(),
        };
    }

    public function getStatusClass(?string $status): string
    {
        return match ($status) {
            'done' => 'rem-cal-event--done',
            'cancel' => 'rem-cal-event--cancel',
            default => 'rem-cal-event--pending',
        };
    }
}