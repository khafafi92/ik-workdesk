<?php

namespace App\Filament\Resources\VehicleBookings\Schemas;

use App\Models\Vehicle;
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

class VehicleBookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Book Vehicle')
                ->description(
                    'Pilih kendaraan, waktu keberangkatan, dan durasi pemakaian.'
                )
                ->schema([
                    TextInput::make('title')
                        ->label('Trip Title')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Select::make('vehicle_id')
                        ->label('Vehicle')
                        ->relationship(
                            name: 'vehicle',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query
                                ->where('is_active', true)
                                ->orderBy('name')
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn (Vehicle $vehicle): string => $vehicle->name
                                .' · '
                                .$vehicle->plate_number
                                .' · '
                                .$vehicle->capacity
                                .' people'
                        )
                        ->default(
                            fn () => request()->integer('vehicle_id')
                                ?: null
                        )
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('destination')
                        ->label('Destination')
                        ->required()
                        ->maxLength(255),
                    DatePicker::make('booking_date')
                        ->label('Booking Date')
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
                            fn (): string => request()->query('start_at')
                                    ? Carbon::parse(
                                        request()->query('start_at')
                                    )->format('H:i')
                                    : '08:00'
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
                            '4' => '4 hours',
                            '5' => '5 hours',
                            '6' => '6 hours',
                            '8' => '8 hours',
                            '10' => '10 hours',
                            '12' => '12 hours',
                        ])
                        ->default('1')
                        ->selectablePlaceholder(false)
                        ->live()
                        ->required(),
                    Placeholder::make('calculated_end')
                        ->label('Recorded Schedule')
                        ->content(
                            function (Get $get): string {
                                if (
                                    blank($get('booking_date'))
                                    || blank($get('start_time'))
                                    || blank($get('duration_hours'))
                                ) {
                                    return '-';
                                }

                                $start = Carbon::parse(
                                    $get('booking_date')
                                    .' '
                                    .$get('start_time')
                                );
                                $end = $start->copy()->addMinutes(
                                    (int) round(
                                        ((float) $get('duration_hours'))
                                        * 60
                                    )
                                );

                                return $start->format('d/m/Y H:i')
                                    .' – '
                                    .$end->format('d/m/Y H:i');
                            }
                        ),
                    TextInput::make('passengers_count')
                        ->label('Number of Passengers')
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required(),
                    TextInput::make('driver_name')
                        ->label('Driver')
                        ->placeholder('Optional')
                        ->maxLength(255),
                    Textarea::make('purpose')
                        ->label('Purpose')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Requester')
                ->schema([
                    Placeholder::make('requester_name')
                        ->label('Requester')
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
