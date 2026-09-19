<?php

namespace App\Filament\Resources\WorkLocations\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class WorkLocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('gps_name')
                    ->label('Nama Kelompok Lokasi')
                    ->helperText('Untuk beberapa titik di lokasi yang sama, gunakan nama yang sama, misalnya KPMOG Office.')
                    ->maxLength(255)
                    ->required(),
                Textarea::make('address')
                    ->label('Keterangan Titik')
                    ->helperText('Contoh: pintu depan, gedung utama, parkiran, atau pintu belakang.')
                    ->columnSpanFull(),
                TextInput::make('latitude')
                    ->label('Latitude')
                    ->helperText('Contoh: -6.2849340')
                    ->numeric()
                    ->minValue(-90)
                    ->maxValue(90)
                    ->required(),
                TextInput::make('longitude')
                    ->label('Longitude')
                    ->helperText('Contoh: 106.8069252')
                    ->numeric()
                    ->minValue(-180)
                    ->maxValue(180)
                    ->required(),
                TextInput::make('radius_meters')
                    ->label('Radius (meter)')
                    ->helperText('Gunakan 30–75 meter sebagai awal; sesuaikan berdasarkan hasil GPS di lapangan.')
                    ->required()
                    ->numeric()
                    ->minValue(5)
                    ->maxValue(1000)
                    ->default(50),
                Toggle::make('is_flexible')
                    ->label('Lokasi Fleksibel')
                    ->helperText('Aktifkan hanya jika absensi memang boleh dilakukan dari lokasi mana pun.')
                    ->default(false),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}
