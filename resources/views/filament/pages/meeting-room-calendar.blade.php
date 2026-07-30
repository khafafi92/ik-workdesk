<x-filament-panels::page>
    @php
        $calendar = $this->getCalendarData();
    @endphp

    <div class="mr-calendar-page">
        <section class="mr-calendar-hero">
            <div>
                <p class="mr-calendar-eyebrow">MEETING ROOM</p>
                <h1>Weekly room schedule</h1>
                <p>
                    Lihat ketersediaan seluruh ruangan dan booking jadwal tanpa bentrok.
                </p>
            </div>

            <div class="mr-calendar-nav">
                <button type="button" wire:click="previousWeek" aria-label="Previous week">
                    <x-heroicon-o-chevron-left />
                </button>
                <button type="button" wire:click="currentWeek" class="mr-calendar-today">
                    Today
                </button>
                <button type="button" wire:click="nextWeek" aria-label="Next week">
                    <x-heroicon-o-chevron-right />
                </button>
            </div>
        </section>

        <div class="mr-calendar-period">
            {{ $calendar['start']->format('d M') }}
            –
            {{ $calendar['end']->format('d M Y') }}
        </div>

        @if ($calendar['rooms']->isEmpty())
            <div class="mr-calendar-empty">
                <x-heroicon-o-building-office-2 />
                <strong>Belum ada ruangan aktif</strong>
                <p>Admin dapat menambahkan atau mengaktifkan ruangan melalui menu Meeting Rooms.</p>
            </div>
        @else
            <div class="mr-calendar-scroll">
                <div class="mr-calendar-grid">
                    <div class="mr-calendar-corner">Room</div>

                    @foreach ($calendar['days'] as $day)
                        <div @class([
                            'mr-calendar-day',
                            'is-today' => $day->isToday(),
                        ])>
                            <span>{{ $day->format('D') }}</span>
                            <strong>{{ $day->format('d') }}</strong>
                            <small>{{ $day->format('M') }}</small>
                        </div>
                    @endforeach

                    @foreach ($calendar['rooms'] as $room)
                        <div class="mr-calendar-room">
                            <strong>{{ $room->name }}</strong>
                            <span>
                                {{ $room->location ?: 'Location not set' }}
                                · {{ $room->capacity }} people
                            </span>
                            <small>
                                {{ substr($room->available_from, 0, 5) }}
                                –{{ substr($room->available_until, 0, 5) }}
                            </small>
                        </div>

                        @foreach ($calendar['days'] as $day)
                            @php
                                $key = $room->id.'|'.$day->format('Y-m-d');
                                $dayBookings = $calendar['bookings']->get($key, collect());
                            @endphp

                            <div @class([
                                'mr-calendar-cell',
                                'is-today' => $day->isToday(),
                            ])>
                                @forelse ($dayBookings as $booking)
                                    @php
                                        $canView = auth()->user()?->can('view', $booking) === true;
                                        $canEdit = \App\Filament\Resources\MeetingBookings\MeetingBookingResource::canEdit($booking);
                                    @endphp

                                    <a
                                        href="{{ $canEdit
                                            ? \App\Filament\Resources\MeetingBookings\MeetingBookingResource::getUrl('edit', ['record' => $booking])
                                            : \App\Filament\Resources\MeetingBookings\MeetingBookingResource::getUrl('index') }}"
                                        class="mr-calendar-event"
                                        wire:navigate
                                    >
                                        <time>
                                            {{ $booking->start_at->format('H:i') }}
                                            –{{ $booking->end_at->format('H:i') }}
                                        </time>
                                        <strong>{{ $canView ? $booking->title : 'Booked' }}</strong>
                                        <span>{{ $canView ? ($booking->organizer?->name ?? '-') : 'Reserved' }}</span>
                                    </a>
                                @empty
                                    @if ($day->copy()->endOfDay()->isPast())
                                        <span class="mr-calendar-open is-past">
                                            <span>Past</span>
                                        </span>
                                    @elseif ($this->canCreateBooking())
                                        <a
                                            href="{{ $this->getBookUrl($room->id, $day->format('Y-m-d')) }}"
                                            class="mr-calendar-open"
                                            wire:navigate
                                        >
                                            <x-heroicon-o-plus />
                                            <span>Available</span>
                                        </a>
                                    @else
                                        <span class="mr-calendar-open">
                                            <span>Available</span>
                                        </span>
                                    @endif
                                @endforelse
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mr-calendar-legend">
            <span><i class="is-booked"></i> Booked</span>
            <span><i class="is-open"></i> Available</span>
            <span>Calendar menggunakan waktu Asia/Jakarta.</span>
        </div>
    </div>
</x-filament-panels::page>
