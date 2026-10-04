<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class unitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $units = [
            [
                'type' => '1-Bedroom',
                'room_no' => '101',
                'room_price' => 2000,
                'status' => 'vacant',
                'capacity' => 2,
                'no_of_occupants' => 0,
                'application_limit' => 3,
            ],
            [
                'type' => '1-Bedroom',
                'room_no' => '102',
                'room_price' => 2100,
                'status' => 'vacant',
                'capacity' => 2,
                'no_of_occupants' => 0,
                'application_limit' => 3,
            ],
            [
                'type' => '2-Bedroom',
                'room_no' => '103',
                'room_price' => 4200,
                'status' => 'vacant',
                'capacity' => 2,
                'no_of_occupants' => 0,
                'application_limit' => 4,
            ],
            [
                'type' => '2-Bedroom',
                'room_no' => '104',
                'room_price' => 4100,
                'status' => 'vacant',
                'capacity' => 2,
                'no_of_occupants' => 0,
                'application_limit' => 4,
            ],
            [
                'type' => 'Bed-Spacer',
                'room_no' => '105',
                'room_price' => 1000,
                'status' => 'vacant',
                'capacity' => 4,
                'no_of_occupants' => 0,
                'application_limit' => 8,
            ],
        ];

        foreach ($units as $unit) {
            Unit::create($unit);
        }
    }
}
