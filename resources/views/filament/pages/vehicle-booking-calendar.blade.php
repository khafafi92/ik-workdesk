<x-filament-panels::page>
    @php
        $calendar = $this->getCalendarData();
    @endphp

    <div class="mr-calendar-page">
        <section class="mr-calendar-hero">
            <div>
                <p class="mr-calendar-eyebrow">VEHICLE BOOKING</p>
                <h1>Weekly vehicle schedule</h1>
                <p>
                    Lihat kendaraan yang tersedia dan rencanakan perjalanan tanpa jadwal bentrok.
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

        @if ($calendar['vehicles']->isEmpty())
            <div class="mr-calendar-empty">
                <x-heroicon-o-truck />
                <strong>Belum ada kendaraan aktif</strong>
                <p>Admin atau GA dapat menambahkan kendaraan melalui menu Vehicles.</p>
            </div>
        @else
            <div class="mr-calendar-scroll">
                <div class="mr-calendar-grid">
                    <div class="mr-calendar-corner">Vehicle</div>

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

                    @foreach ($calendar['vehicles'] as $vehicle)
                        <div class="mr-calendar-room">
                            <strong>{{ $vehicle->name }}</strong>
                            <span>
                                {{ $vehicle->plate_number }}
                                · {{ $vehicle->capacity }} people
                            </span>
                            <small>
                                {{ substr($vehicle->available_from, 0, 5) }}
                                –{{ substr($vehicle->available_until, 0, 5) }}
                            </small>
                        </div>

                        @foreach ($calendar['days'] as $day)
                            @php
                                $key = $vehicle->id.'|'.$day->format('Y-m-d');
                                $dayBookings = $calendar['bookings']->get($key, collect());
                            @endphp

                            <div @class([
                                'mr-calendar-cell',
                                'is-today' => $day->isToday(),
                            ])>
                                @forelse ($dayBookings as $booking)
                                    @php
                                        $canView = auth()->user()?->can('view', $booking) === true;
                                        $canEdit = \App\Filament\Resources\VehicleBookings\VehicleBookingResource::canEdit($booking);
                                    @endphp

                                    <a
                                        href="{{ $canEdit
                                            ? \App\Filament\Resources\VehicleBookings\VehicleBookingResource::getUrl('edit', ['record' => $booking])
                                            : \App\Filament\Resources\VehicleBookings\VehicleBookingResource::getUrl('index') }}"
                                        class="mr-calendar-event"
                                        wire:navigate
                                    >
                                        <time>
                                            {{ $booking->start_at->format('H:i') }}
                                            –{{ $booking->end_at->format('H:i') }}
                                        </time>
                                        <strong>{{ $canView ? $booking->destination : 'Booked' }}</strong>
                                        <span>{{ $canView ? ($booking->requester?->name ?? '-') : 'Reserved' }}</span>
                                    </a>
                                @empty
                                    @if ($day->copy()->endOfDay()->isPast())
                                        <span class="mr-calendar-open is-past">
                                            <span>Past</span>
                                        </span>
                                    @elseif ($this->canCreateBooking())
                                        <a
                                            href="{{ $this->getBookUrl($vehicle->id, $day->format('Y-m-d')) }}"
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
