<?php

namespace Database\Factories;

use App\Enums\ItRequestStatus;
use App\Models\Branch;
use App\Models\ErpModule;
use App\Models\ErpRepairRequest;
use App\Models\ItRequestType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ErpRepairRequest>
 */
class ErpRepairRequestFactory extends Factory
{
    private static array $issues = [
        'Tidak bisa login ke modul', 'Data tidak tersimpan dengan benar', 'Laporan error saat generate',
        'Tombol tidak berfungsi', 'Tampilan tabel berantakan', 'Kalkulasi salah di halaman ini',
        'Import data gagal', 'Export PDF gagal dicetak', 'Koneksi database terputus',
        'Fitur pencarian tidak akurat', 'Notifikasi tidak muncul', 'Permission error saat akses menu',
    ];

    public function definition(): array
    {
        $status = fake()->randomElement(ItRequestStatus::cases());
        $isAssigned = $status !== ItRequestStatus::Submitted;

        return [
            'requester_id' => User::inRandomOrder()->value('id'),
            'branch_id' => Branch::inRandomOrder()->value('id'),
            'assignee_id' => $isAssigned ? User::inRandomOrder()->value('id') : null,
            'erp_module_id' => ErpModule::inRandomOrder()->value('id'),
            'request_type_id' => ItRequestType::inRandomOrder()->value('id'),
            'keterangan' => fake()->randomElement(self::$issues).'. '.fake()->sentence(8),
            'attachments' => null,
            'status' => $status,
            'work_classification' => $isAssigned ? fake()->randomElement(['standard', 'major_project']) : null,
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'resolved_at' => $status === ItRequestStatus::Completed ? fake()->dateTimeBetween('-30 days', 'now') : null,
        ];
    }
}
