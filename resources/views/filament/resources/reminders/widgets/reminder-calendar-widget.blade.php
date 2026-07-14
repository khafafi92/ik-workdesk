@php
    $calendar = $this->getCalendarData();
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <div class="rem-cal">
            <div class="rem-cal-header">
                <div>
                    <h2>Reminder Calendar</h2>
                    <p>Calendar view of reminders created for the current month.</p>
                </div>

                <div class="rem-cal-month">
                    {{ $calendar['title'] }}
                </div>
            </div>

            <div class="rem-cal-grid">
                @foreach ($calendar['weekDays'] as $dayName)
                    <div class="rem-cal-weekday">
                        {{ $dayName }}
                    </div>
                @endforeach

                @foreach ($calendar['weeks'] as $week)
                    @foreach ($week as $day)
                        <div @class([
                            'rem-cal-day',
                            'rem-cal-day--muted' => !$day['isCurrentMonth'],
                            'rem-cal-day--today' => $day['isToday'],
                        ])>
                            <div class="rem-cal-date">
                                {{ $day['date']->format('j') }}
                            </div>

                            <div class="rem-cal-events">
                                @forelse ($day['reminders'] as $reminder)
                                    <a href="{{ \App\Filament\Resources\Reminders\ReminderResource::getUrl('view', ['record' => $reminder]) }}"
                                        class="rem-cal-event {{ $this->getStatusClass($reminder->status) }}">
                                        <span class="rem-cal-event-time">
                                            {{ $reminder->reminder_at->format('H:i') }}
                                        </span>

                                        <span class="rem-cal-event-title">
                                            {{ $reminder->title }}
                                        </span>

                                        <span class="rem-cal-event-type">
                                            {{ $this->formatReminderType($reminder->reminder_type) }}
                                        </span>
                                    </a>
                                @empty
                                    <span class="rem-cal-empty">—</span>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
