<?php

namespace App\Filament\Resources\MeetingRooms\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MeetingRoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Room Information')
                ->description(
                    'Nama ruangan dapat diubah kapan saja tanpa menghapus riwayat booking.'
                )
                ->schema([
                    TextInput::make('name')
                        ->label('Room Name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('code')
                        ->label('Room Code')
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true),

                    TextInput::make('location')
                        ->label('Location')
                        ->maxLength(255),

                    TextInput::make('capacity')
                        ->label('Capacity')
                        ->numeric()
                        ->minValue(1)
                        ->default(8)
                        ->required(),

                    TimePicker::make('available_from')
                        ->label('Available From')
                        ->seconds(false)
                        ->default('08:00')
                        ->required(),

                    TimePicker::make('available_until')
                        ->label('Available Until')
                        ->seconds(false)
                        ->default('18:00')
                        ->required()
                        ->after('available_from'),

                    Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->columnSpanFull(),

                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->required(),
                ])
                ->columns(2),

            Section::make('Facilities')
                ->schema([
                    Toggle::make('has_display')
                        ->label('TV / Display'),
                    Toggle::make('has_projector')
                        ->label('Projector'),
                    Toggle::make('has_video_conference')
                        ->label('Video Conference'),
                    Toggle::make('has_whiteboard')
                        ->label('Whiteboard'),
                ])
                ->columns(2),
        ]);
    }
}
