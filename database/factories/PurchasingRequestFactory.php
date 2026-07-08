<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\PurchasingRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchasingRequest>
 */
class PurchasingRequestFactory extends Factory
{
    private static array $keperluan = [
        'Pembelian perlengkapan kantor untuk kebutuhan operasional bulan ini',
        'Pengadaan alat kebersihan dan sanitasi untuk semua cabang',
        'Pembelian spare part mesin produksi yang sudah aus',
        'Kebutuhan ATK untuk kegiatan pelatihan karyawan baru',
        'Pengadaan peralatan IT untuk tim teknisi lapangan',
        'Pembelian bahan baku tambahan untuk memenuhi permintaan pesanan meningkat',
        'Kebutuhan logistik pengiriman bulanan cabang selatan',
        'Pembelian alat safety & K3 sesuai regulasi terbaru',
    ];

    public function definition(): array
    {
        $status = fake()->randomElement(RequestStatus::cases());
        $isApproved = in_array($status, [RequestStatus::Approved, RequestStatus::InProgress, RequestStatus::Completed]);

        return [
            'requester_id' => User::inRandomOrder()->value('id'),
            'approved_by' => $isApproved ? User::inRandomOrder()->value('id') : null,
            'keperluan' => fake()->randomElement(self::$keperluan),
            'status' => $status,
            'approved_at' => $isApproved ? fake()->dateTimeBetween('-20 days', 'now') : null,
            'notes' => fake()->optional(0.4)->sentence(8),
        ];
    }
}
