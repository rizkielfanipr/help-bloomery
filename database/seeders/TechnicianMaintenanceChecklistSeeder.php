<?php

namespace Database\Seeders;

use App\Models\TechnicianMaintenanceChecklist;
use Illuminate\Database\Seeder;

class TechnicianMaintenanceChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['question' => 'Periksa kondisi chiller, freezer, dan showcase.', 'check_procedure' => 'Pastikan suhu dan fungsi pendinginan normal.', 'requires_photo' => true, 'sort_order' => 1],
            ['question' => 'Periksa mesin kopi, kompor, dan blender.', 'check_procedure' => 'Nyalakan peralatan dan cek suara atau kebocoran abnormal.', 'requires_photo' => true, 'sort_order' => 2],
            ['question' => 'Periksa instalasi listrik dan panel utama.', 'check_procedure' => 'Pastikan tidak ada kabel longgar, panas, atau terbakar.', 'requires_photo' => true, 'sort_order' => 3],
            ['question' => 'Periksa saluran air dan potensi kebocoran.', 'check_procedure' => 'Periksa area sink, floor drain, dan sambungan pipa.', 'requires_photo' => false, 'sort_order' => 4],
            ['question' => 'Periksa APAR dan akses jalur keselamatan.', 'check_procedure' => 'Pastikan APAR tersedia, tidak kedaluwarsa, dan mudah diakses.', 'requires_photo' => true, 'sort_order' => 5],
        ];

        foreach ($items as $item) {
            TechnicianMaintenanceChecklist::updateOrCreate(['question' => $item['question']], [...$item, 'is_active' => true]);
        }
    }
}
