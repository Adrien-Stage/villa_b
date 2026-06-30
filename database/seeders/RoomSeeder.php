<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = Tenant::where('slug', 'villa-boutanga')->value('id');

        if (!$tenantId) {
            return;
        }

        $roomTypes = RoomType::query()
            ->pluck('id', 'code');

        $rooms = [
            ['number' => '101', 'floor' => '1', 'view_type' => 'garden', 'room_type_id' => $roomTypes['STD'] ?? null],
            ['number' => '102', 'floor' => '1', 'view_type' => 'garden', 'room_type_id' => $roomTypes['STD'] ?? null],
            ['number' => '103', 'floor' => '1', 'view_type' => 'courtyard', 'room_type_id' => $roomTypes['STD'] ?? null],
            ['number' => '104', 'floor' => '1', 'view_type' => 'pool', 'room_type_id' => $roomTypes['SUP'] ?? null],
            ['number' => '105', 'floor' => '1', 'view_type' => 'pool', 'room_type_id' => $roomTypes['SUP'] ?? null],
            ['number' => '201', 'floor' => '2', 'view_type' => 'garden', 'room_type_id' => $roomTypes['STD'] ?? null],
            ['number' => '202', 'floor' => '2', 'view_type' => 'garden', 'room_type_id' => $roomTypes['STD'] ?? null],
            ['number' => '203', 'floor' => '2', 'view_type' => 'heritage', 'room_type_id' => $roomTypes['SUP'] ?? null],
            ['number' => '204', 'floor' => '2', 'view_type' => 'pool', 'room_type_id' => $roomTypes['SJR'] ?? null],
            ['number' => '301', 'floor' => '3', 'view_type' => 'heritage', 'room_type_id' => $roomTypes['SPRES'] ?? null],
        ];

        foreach ($rooms as $room) {
            if (!$room['room_type_id']) {
                continue;
            }

            Room::updateOrCreate(
                [
                    'number' => $room['number'],
                ],
                array_merge($room, [
                    'status' => 'available',
                    'is_active' => true,
                ])
            );
        }
    }
}
