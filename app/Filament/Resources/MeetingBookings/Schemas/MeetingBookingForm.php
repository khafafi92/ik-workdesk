<?php

namespace App\Filament\Resources\MeetingBookings\Schemas;

use App\Models\MeetingRoom;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class MeetingBookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Book Meeting Room')
                ->description(
                    'Pilih ruangan dan waktu. Sistem akan otomatis menolak jadwal yang bertabrakan.'
                )
                ->schema([
                    TextInput::make('title')
                        ->label('Meeting Title')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Select::make('meeting_room_id')
                        ->label('Meeting Room')
                        ->relationship(
                            name: 'room',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query
                                ->where('is_active', true)
                                ->orderBy('name')
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn (MeetingRoom $room): string => $room->name
                                .' · '
                                .($room->location ?: 'Location not set')
                                .' · '
                                .$room->capacity
                                .' people'
                        )
                        ->searchable()
                        ->preload()
                        ->default(
                            fn () => request()->integer('meeting_room_id')
                                ?: null
                        )
                        ->required(),

                    DatePicker::make('meeting_date')
                        ->label('Meeting Date')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->minDate(today())
                        ->default(
                            fn () => request()->query('start_at')
                                    ? Carbon::parse(
                                        request()->query('start_at')
                                    )->toDateString()
                                    : today()->toDateString()
                        )
                        ->live()
                        ->required(),

                    TimePicker::make('start_time')
                        ->label('Start Time')
                        ->native(false)
                        ->displayFormat('H:i')
                        ->seconds(false)
                        ->minutesStep(15)
                        ->default(
                            function (): string {
                                if (request()->query('start_at')) {
                                    return Carbon::parse(
                                        request()->query('start_at')
                                    )->format('H:i');
                                }

                                $now = now()->seconds(0);
                                $minutesToNextQuarter =
                                    15 - ($now->minute % 15);

                                return $now
                                    ->addMinutes($minutesToNextQuarter)
                                    ->format('H:i');
                            }
                        )
                        ->live()
                        ->required(),

                    Select::make('duration_hours')
                        ->label('Duration')
                        ->options([
                            '0.5' => '30 minutes',
                            '1' => '1 hour',
                            '1.5' => '1.5 hours',
                            '2' => '2 hours',
                            '2.5' => '2.5 hours',
                            '3' => '3 hours',
                            '3.5' => '3.5 hours',
                            '4' => '4 hours',
                            '5' => '5 hours',
                            '6' => '6 hours',
                            '7' => '7 hours',
                            '8' => '8 hours',
                        ])
                        ->default(
                            function (): string {
                                if (
                                    request()->query('start_at')
                                    && request()->query('end_at')
                                ) {
                                    $minutes = Carbon::parse(
                                        request()->query('start_at')
                                    )->diffInMinutes(
                                        Carbon::parse(
                                            request()->query('end_at')
                                        )
                                    );

                                    return (string) ($minutes / 60);
                                }

                                return '1';
                            }
                        )
                        ->selectablePlaceholder(false)
                        ->live()
                        ->required(),

                    Placeholder::make('calculated_end')
                        ->label('Recorded Schedule')
                        ->content(
                            function (Get $get): string {
                                if (
                                    blank($get('meeting_date'))
                                    || blank($get('start_time'))
                                    || blank($get('duration_hours'))
                                ) {
                                    return '-';
                                }

                                $start = Carbon::parse(
                                    $get('meeting_date')
                                    .' '
                                    .$get('start_time')
                                );
                                $end = $start
                                    ->copy()
                                    ->addMinutes(
                                        (int) round(
                                            ((float) $get(
                                                'duration_hours'
                                            )) * 60
                                        )
                                    );

                                return $start->format('d/m/Y H:i')
                                    .' – '
                                    .$end->format('d/m/Y H:i');
                            }
                        ),

                    Select::make('participants')
                        ->label('Participants')
                        ->options(
                            fn (): array => User::query()
                                ->whereKeyNot(auth()->id())
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all()
                        )
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->columnSpanFull(),

                    Select::make('meeting_type')
                        ->label('Meeting Type')
                        ->options([
                            'onsite' => 'On-site',
                            'hybrid' => 'Hybrid',
                            'online' => 'Online',
                        ])
                        ->default('onsite')
                        ->required(),

                    TextInput::make('meeting_link')
                        ->label('Meeting Link')
                        ->url()
                        ->maxLength(255)
                        ->placeholder('https://teams.microsoft.com/...'),

                    Textarea::make('external_guests')
                        ->label('External Guests')
                        ->placeholder('Nama tamu eksternal, pisahkan dengan koma.')
                        ->rows(2)
                        ->columnSpanFull(),

                    Textarea::make('agenda')
                        ->label('Purpose / Agenda')
                        ->rows(4)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Organizer')
                ->schema([
                    Placeholder::make('organizer_name')
                        ->label('Organizer')
                        ->content(
                            fn (): string => auth()->user()?->name ?? '-'
                        ),
                    Placeholder::make('department_name')
                        ->label('Department')
                        ->content(
                            fn (): string => auth()->user()?->employee?->department?->name
                                ?? '-'
                        ),
                ])
                ->columns(2)
                ->visibleOn('create'),
        ]);
    }
}
