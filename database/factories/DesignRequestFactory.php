<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\Branch;
use App\Models\DesignCategory;
use App\Models\DesignRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DesignRequest>
 */
class DesignRequestFactory extends Factory
{
    private static array $titles = [
        'Banner Promo Ramadan', 'Desain Poster Event Bulanan', 'Flyer Diskon Akhir Tahun',
        'Logo Cabang Baru', 'Template Instagram Story', 'Spanduk Grand Opening',
        'Kartu Nama Karyawan Baru', 'Desain Kemasan Produk', 'Brosur Layanan Terbaru',
        'Backdrop Foto Acara', 'Banner Digital Sosmed', 'Infografis Laporan Bulanan',
        'Desain Seragam Karyawan', 'Template Sertifikat Pelatihan', 'Poster Menu Terbaru',
    ];

    public function definition(): array
    {
        $status = fake()->randomElement(RequestStatus::cases());
        $isAssigned = ! in_array($status, [RequestStatus::Draft, RequestStatus::Submitted]);

        return [
            'requester_id' => User::inRandomOrder()->value('id'),
            'branch_id' => Branch::inRandomOrder()->value('id'),
            'assignee_id' => $isAssigned ? User::inRandomOrder()->value('id') : null,
            'design_category_id' => DesignCategory::inRandomOrder()->value('id'),
            'judul_permintaan' => fake()->randomElement(self::$titles),
            'ringkasan_brief' => fake()->paragraph(3),
            'attachments' => null,
            'status' => $status,
            'resolved_at' => $status === RequestStatus::Completed ? fake()->dateTimeBetween('-30 days', 'now') : null,
        ];
    }
}
