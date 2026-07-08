<?php

namespace Database\Factories;

use App\Models\PurchasingRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchasingRequestItem>
 */
class PurchasingRequestItemFactory extends Factory
{
    private static array $items = [
        ['nama' => 'Kertas HVS A4', 'satuan' => 'rim', 'price' => 45000],
        ['nama' => 'Tinta Printer Canon', 'satuan' => 'botol', 'price' => 85000],
        ['nama' => 'Pulpen Ballpoint', 'satuan' => 'lusin', 'price' => 25000],
        ['nama' => 'Stapler Joyko', 'satuan' => 'pcs', 'price' => 35000],
        ['nama' => 'Flash Disk 32GB', 'satuan' => 'pcs', 'price' => 120000],
        ['nama' => 'Baterai AA', 'satuan' => 'pack', 'price' => 30000],
        ['nama' => 'Tissue Roll', 'satuan' => 'pack', 'price' => 50000],
        ['nama' => 'Sabun Cuci Tangan', 'satuan' => 'botol', 'price' => 22000],
        ['nama' => 'Kabel USB Type-C', 'satuan' => 'pcs', 'price' => 65000],
        ['nama' => 'Mouse Logitech', 'satuan' => 'pcs', 'price' => 150000],
        ['nama' => 'Helm Safety', 'satuan' => 'pcs', 'price' => 200000],
        ['nama' => 'Sarung Tangan Kerja', 'satuan' => 'pasang', 'price' => 45000],
    ];

    public function definition(): array
    {
        $item = fake()->randomElement(self::$items);

        return [
            'nama_barang' => $item['nama'],
            'jumlah' => fake()->numberBetween(1, 20),
            'satuan' => $item['satuan'],
            'spesifikasi' => fake()->optional(0.5)->sentence(5),
            'estimated_price' => $item['price'] * fake()->numberBetween(1, 3),
        ];
    }
}
