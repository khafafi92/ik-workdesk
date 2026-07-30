<?php

namespace Database\Seeders;

use App\Models\MeetingRoom;
use Illuminate\Database\Seeder;

class MeetingRoomSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            ['code' => 'RMEET-L9', 'name' => 'RMeet Lt. 9', 'location' => 'Lantai 9'],
            ['code' => 'RMEET-L2Y', 'name' => 'RMeet Lt. 2 (Yayasan)', 'location' => 'Lantai 2'],
            ['code' => 'RMEET-L2R', 'name' => 'RMeet Lt. 2 (S. Resepsionis)', 'location' => 'Lantai 2'],
            ['code' => 'RMEET-J', 'name' => 'RMeet J', 'location' => null],
            ['code' => 'RMEET-K', 'name' => 'RMeet K', 'location' => null],
            ['code' => 'RMEET-L', 'name' => 'RMeet L', 'location' => null],
        ];

        foreach ($rooms as $room) {
            MeetingRoom::query()->updateOrCreate(
                ['code' => $room['code']],
                [
                    ...$room,
                    'capacity' => 8,
                    'available_from' => '08:00:00',
                    'available_until' => '18:00:00',
                    'is_active' => true,
                ]
            );
        }
    }
}
