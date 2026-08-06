<?php

namespace Database\Factories;

use App\Enums\ContentRequestStatus;
use App\Models\Branch;
use App\Models\ContentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentRequest>
 */
class ContentRequestFactory extends Factory
{
    private static array $titles = [
        'Video Promo Menu Baru', 'Foto Produk Katalog Online', 'Reels Behind The Scene Dapur',
        'Video Testimoni Pelanggan', 'Foto Interior Cabang Baru', 'Video Tutorial Penyajian Menu',
        'Foto Campaign Hari Kemerdekaan', 'Video Highlight Event Cabang', 'Foto Produk untuk E-Commerce',
    ];

    private static array $platforms = ['Instagram', 'TikTok', 'YouTube', 'Facebook', 'Website'];

    public function definition(): array
    {
        $status = fake()->randomElement(ContentRequestStatus::cases());

        return [
            'requester_id' => User::inRandomOrder()->value('id'),
            'branch_id' => Branch::inRandomOrder()->value('id'),
            'judul_konten' => fake()->randomElement(self::$titles),
            'jenis_konten' => fake()->randomElement(['photo', 'video']),
            'platform_tujuan' => fake()->randomElement(self::$platforms),
            'tujuan_konten' => fake()->paragraph(2),
            'link_contoh_konten' => fake()->optional()->url(),
            'attachments' => null,
            'status' => $status,
            'resolved_at' => $status === ContentRequestStatus::Completed ? fake()->dateTimeBetween('-30 days', 'now') : null,
        ];
    }
}
