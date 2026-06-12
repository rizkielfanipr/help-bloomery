<?php

namespace Database\Seeders;

use App\Models\CasualPosition;
use App\Models\CasualPositionOpening;
use App\Models\CasualShift;
use App\Models\User;
use Illuminate\Database\Seeder;

class CasualTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $poster = User::where('email', 'hr@bloomery.test')->first()
            ?? User::where('email', 'admin@bloomery.org')->first();

        // Positions
        $kasir = CasualPosition::firstOrCreate(
            ['name' => 'Kasir'],
            ['fee_per_day' => 120000, 'description' => 'Melayani transaksi di kasir.', 'is_active' => true],
        );

        $loader = CasualPosition::firstOrCreate(
            ['name' => 'Loader'],
            ['fee_per_day' => 100000, 'description' => 'Bongkar muat barang di gudang.', 'is_active' => true],
        );

        $picker = CasualPosition::firstOrCreate(
            ['name' => 'Picker'],
            ['fee_per_day' => 110000, 'description' => 'Picking barang sesuai order.', 'is_active' => true],
        );

        // Shifts
        $shiftPagi = CasualShift::firstOrCreate(
            ['name' => 'Shift Pagi'],
            [
                'start_time' => '07:00:00',
                'end_time' => '15:00:00',
                'tolerance_late_minutes' => 15,
                'tolerance_early_out_minutes' => 15,
                'location_required' => false,
                'location_radius_meters' => 100,
                'is_active' => true,
            ],
        );

        $shiftSiang = CasualShift::firstOrCreate(
            ['name' => 'Shift Siang'],
            [
                'start_time' => '12:00:00',
                'end_time' => '20:00:00',
                'tolerance_late_minutes' => 15,
                'tolerance_early_out_minutes' => 15,
                'location_required' => false,
                'location_radius_meters' => 100,
                'is_active' => true,
            ],
        );

        // Openings for the next 3 days
        $openings = [
            [
                'casual_position_id' => $kasir->id,
                'casual_shift_id' => $shiftPagi->id,
                'location' => 'Toko Utama Lt. 1',
                'work_date' => today()->addDay(),
                'total_slots' => 3,
                'description' => 'Bawa seragam hitam-putih.',
            ],
            [
                'casual_position_id' => $loader->id,
                'casual_shift_id' => $shiftPagi->id,
                'location' => 'Gudang Blok A',
                'work_date' => today()->addDay(),
                'total_slots' => 5,
                'description' => null,
            ],
            [
                'casual_position_id' => $picker->id,
                'casual_shift_id' => $shiftSiang->id,
                'location' => 'Gudang Blok B',
                'work_date' => today()->addDays(2),
                'total_slots' => 4,
                'description' => 'Wajib pakai safety shoes.',
            ],
            [
                'casual_position_id' => $kasir->id,
                'casual_shift_id' => $shiftSiang->id,
                'location' => 'Toko Utama Lt. 2',
                'work_date' => today()->addDays(3),
                'total_slots' => 2,
                'description' => null,
            ],
        ];

        foreach ($openings as $data) {
            CasualPositionOpening::firstOrCreate(
                [
                    'casual_position_id' => $data['casual_position_id'],
                    'work_date' => $data['work_date'],
                    'location' => $data['location'],
                ],
                array_merge($data, [
                    'is_active' => true,
                    'posted_by' => $poster->id,
                ]),
            );
        }
    }
}
