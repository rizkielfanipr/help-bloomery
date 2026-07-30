<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\CasualClockRecord;
use App\Models\CasualPosition;
use App\Models\CasualPositionOpening;
use App\Models\CasualPositionRegistration;
use App\Models\User;
use Carbon\Carbon;
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

        // Branches
        $branchUtama = Branch::firstOrCreate(
            ['name' => 'Toko Utama'],
            ['address' => 'Jl. Sudirman No. 1, Jakarta Pusat', 'lat' => -6.2088, 'lng' => 106.8456, 'radius_meters' => 150, 'is_active' => true],
        );
        $branchGudang = Branch::firstOrCreate(
            ['name' => 'Gudang'],
            ['address' => 'Jl. Industri No. 10, Bekasi', 'lat' => -6.2383, 'lng' => 106.9756, 'radius_meters' => 200, 'is_active' => true],
        );
        $branchCabang = Branch::firstOrCreate(
            ['name' => 'Cabang Selatan'],
            ['address' => 'Jl. TB Simatupang No. 5, Jakarta Selatan', 'lat' => -6.3013, 'lng' => 106.7886, 'radius_meters' => 100, 'is_active' => true],
        );

        // Openings: today (for attendance testing) + next 3 days
        $openings = [
            // Today - untuk test absensi
            ['casual_position_id' => $kasir->id, 'branch_id' => $branchUtama->id, 'work_date' => today(), 'total_slots' => 5, 'description' => 'Opening hari ini untuk test absensi.'],
            ['casual_position_id' => $loader->id, 'branch_id' => $branchGudang->id, 'work_date' => today(), 'total_slots' => 5, 'description' => null],
            ['casual_position_id' => $picker->id, 'branch_id' => $branchGudang->id, 'work_date' => today(), 'total_slots' => 5, 'description' => null],
            // Next 3 days
            ['casual_position_id' => $kasir->id, 'branch_id' => $branchUtama->id, 'work_date' => today()->addDay(), 'total_slots' => 3, 'description' => 'Bawa seragam hitam-putih.'],
            ['casual_position_id' => $loader->id, 'branch_id' => $branchGudang->id, 'work_date' => today()->addDay(), 'total_slots' => 5, 'description' => null],
            ['casual_position_id' => $picker->id, 'branch_id' => $branchGudang->id, 'work_date' => today()->addDays(2), 'total_slots' => 4, 'description' => 'Wajib pakai safety shoes.'],
            ['casual_position_id' => $kasir->id, 'branch_id' => $branchUtama->id, 'work_date' => today()->addDays(3), 'total_slots' => 2, 'description' => null],
            ['casual_position_id' => $picker->id, 'branch_id' => $branchCabang->id, 'work_date' => today()->addDays(2), 'total_slots' => 3, 'description' => null],
        ];

        foreach ($openings as $data) {
            CasualPositionOpening::firstOrCreate(
                ['casual_position_id' => $data['casual_position_id'], 'work_date' => $data['work_date']],
                array_merge($data, ['is_active' => true, 'posted_by' => $poster?->id]),
            );
        }

        // Create casual staff users (idempotent via firstOrCreate on phone/email)
        $staffData = [
            ['name' => 'Budi Santoso', 'phone' => '081234567890', 'bank_name' => 'BCA', 'bank_account_number' => '1234567890', 'position' => $kasir],
            ['name' => 'Siti Rahayu', 'phone' => '082345678901', 'bank_name' => 'BRI', 'bank_account_number' => '2345678901', 'position' => $kasir],
            ['name' => 'Agus Wijaya', 'phone' => '083456789012', 'bank_name' => 'Mandiri', 'bank_account_number' => '3456789012', 'position' => $loader],
            ['name' => 'Dewi Lestari', 'phone' => '084567890123', 'bank_name' => 'BNI', 'bank_account_number' => '4567890123', 'position' => $picker],
            ['name' => 'Eko Prasetyo', 'phone' => '085678901234', 'bank_name' => 'BSI', 'bank_account_number' => '5678901234', 'position' => $loader],
            ['name' => 'Fitri Handayani', 'phone' => '086789012345', 'bank_name' => 'BCA', 'bank_account_number' => '6789012345', 'position' => $picker],
            ['name' => 'Gilang Permana', 'phone' => '087890123456', 'bank_name' => 'CIMB', 'bank_account_number' => '7890123456', 'position' => $kasir],
            ['name' => 'Hana Susanti', 'phone' => '088901234567', 'bank_name' => 'BRI', 'bank_account_number' => '8901234567', 'position' => $picker],
        ];

        $todayOpenings = CasualPositionOpening::where('work_date', today())->get()->keyBy('casual_position_id');

        $users = [];
        foreach ($staffData as $data) {
            [$user, $isNew] = [
                User::firstOrCreate(
                    ['email' => $data['phone'].'@casual.app'],
                    [
                        'name' => $data['name'],
                        'phone' => $data['phone'],
                        'password' => bcrypt('password'),
                        'bank_name' => $data['bank_name'],
                        'bank_account_number' => $data['bank_account_number'],
                        'casual_position_id' => $data['position']->id,
                        'is_active' => true,
                    ]
                ),
                false,
            ];

            if (! $user->hasRole('CASUAL_STAFF')) {
                $user->assignRole('casual_staff');
            }

            $users[] = ['user' => $user, 'position' => $data['position']];

            // Register ke opening hari ini sesuai posisi
            $todayOpening = $todayOpenings->get($data['position']->id);
            if ($todayOpening) {
                CasualPositionRegistration::firstOrCreate([
                    'casual_position_opening_id' => $todayOpening->id,
                    'user_id' => $user->id,
                ]);
            }
        }

        // Skip clock records if already seeded
        if (CasualClockRecord::exists()) {
            return;
        }

        // Create clock records for the past 20 days
        $branches = [$branchUtama, $branchGudang, $branchCabang];

        foreach ($users as $entry) {
            $user = $entry['user'];
            $position = $entry['position'];

            // Each staff works ~14 out of 20 days
            $workDays = collect(range(1, 20))
                ->filter(fn ($d) => fake()->boolean(70))
                ->values();

            foreach ($workDays as $daysAgo) {
                $date = today()->subDays($daysAgo);

                // Skip Sundays
                if ($date->dayOfWeek === Carbon::SUNDAY) {
                    continue;
                }

                $branch = fake()->randomElement($branches);
                $clockIn = $date->copy()->setHour(fake()->numberBetween(7, 9))->setMinute(fake()->randomElement([0, 15, 30, 45]));
                $clockOut = $clockIn->copy()->addHours(fake()->numberBetween(7, 9))->addMinutes(fake()->randomElement([0, 15, 30]));

                CasualClockRecord::create([
                    'user_id' => $user->id,
                    'branch_id' => $branch->id,
                    'date' => $date->toDateString(),
                    'clock_in_at' => $clockIn,
                    'clock_in_lat' => $branch->lat ?? -6.2 + (mt_rand(-100, 100) / 10000),
                    'clock_in_lng' => $branch->lng ?? 106.8 + (mt_rand(-100, 100) / 10000),
                    'clock_out_at' => $clockOut,
                    'clock_out_lat' => $branch->lat ?? -6.2 + (mt_rand(-100, 100) / 10000),
                    'clock_out_lng' => $branch->lng ?? 106.8 + (mt_rand(-100, 100) / 10000),
                    'notes' => null,
                ]);
            }
        }
    }
}
