<?php

namespace Database\Seeders;

use App\Models\TechnicianMaintenanceChecklist;
use Illuminate\Database\Seeder;

class TechnicianMaintenanceChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['section_code' => 'equipment', 'section_name' => 'Peralatan', 'question' => 'Periksa kondisi chiller, freezer, dan showcase.', 'check_procedure' => 'Pastikan suhu dan fungsi pendinginan normal.', 'points' => 2, 'is_critical' => true, 'requires_photo' => true, 'sort_order' => 1],
            ['section_code' => 'equipment', 'section_name' => 'Peralatan', 'question' => 'Periksa mesin kopi, kompor, dan blender.', 'check_procedure' => 'Nyalakan peralatan dan cek suara atau kebocoran abnormal.', 'points' => 2, 'is_critical' => false, 'requires_photo' => true, 'sort_order' => 2],
            ['section_code' => 'utility', 'section_name' => 'Utilitas', 'question' => 'Periksa instalasi listrik dan panel utama.', 'check_procedure' => 'Pastikan tidak ada kabel longgar, panas, atau terbakar.', 'points' => 2, 'is_critical' => true, 'requires_photo' => true, 'sort_order' => 3],
            ['section_code' => 'plumbing', 'section_name' => 'Plumbing', 'question' => 'Periksa saluran air dan potensi kebocoran.', 'check_procedure' => 'Periksa area sink, floor drain, dan sambungan pipa.', 'points' => 1, 'is_critical' => false, 'requires_photo' => false, 'sort_order' => 4],
            ['section_code' => 'safety', 'section_name' => 'Keselamatan', 'question' => 'Periksa APAR dan akses jalur keselamatan.', 'check_procedure' => 'Pastikan APAR tersedia, tidak kedaluwarsa, dan mudah diakses.', 'points' => 2, 'is_critical' => true, 'requires_photo' => true, 'sort_order' => 5],
        ];

        foreach ($items as $item) {
            TechnicianMaintenanceChecklist::updateOrCreate(['question' => $item['question']], [...$item, 'is_active' => true]);
        }
    }
}
