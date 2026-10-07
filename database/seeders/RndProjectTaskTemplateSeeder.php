<?php

namespace Database\Seeders;

use App\Models\RndProjectTaskTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Starter Template Checkpoint (docs/rnd-project-checkpoint-calendar-prd.md §26.5).
 * Idempotent: a template that already exists by name is left untouched, so re-running the seeder
 * never overwrites checkpoints the R&D team has edited.
 */
class RndProjectTaskTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $definition) {
            if (RndProjectTaskTemplate::query()->where('name', $definition['name'])->exists()) {
                continue;
            }

            DB::transaction(function () use ($definition): void {
                $template = RndProjectTaskTemplate::query()->create([
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_active' => true,
                ]);

                foreach ($definition['checkpoints'] as $index => [$title, $taskType, $priority, $description]) {
                    $template->checkpoints()->create([
                        'title' => $title,
                        'task_type' => $taskType,
                        'priority' => $priority,
                        'description' => $description,
                        'sort_order' => $index + 1,
                    ]);
                }
            });
        }
    }

    /**
     * Checkpoint tuple: [title, task_type, priority, description]. Dates are set when applying.
     *
     * @return list<array{name: string, description: string, checkpoints: list<array{0: string, 1: string, 2: string, 3: string}>}>
     */
    private function templates(): array
    {
        return [
            [
                'name' => 'Launching Menu Baru',
                'description' => 'Rangkaian persiapan menu dari trial resep sampai hari launching.',
                'checkpoints' => [
                    ['Trial Resep', 'trial', 'high', 'Uji coba resep final dan catat takaran, waktu proses, serta kendala.'],
                    ['Tasting Internal', 'tasting', 'high', 'Tasting bersama tim internal dan rangkum masukan rasa, tekstur, dan tampilan.'],
                    ['Approval Menu', 'approval', 'urgent', 'Ajukan hasil tasting untuk persetujuan final menu dan harga.'],
                    ['Quality Control', 'quality_control', 'high', 'Verifikasi standar kualitas, shelf life, dan kesesuaian SOP penyajian.'],
                    ['Product Photography', 'product_photography', 'medium', 'Siapkan produk untuk foto menu dan materi promosi.'],
                    ['Launching', 'launching', 'urgent', 'Pastikan menu tersedia di Branch dan tim siap melayani.'],
                ],
            ],
            [
                'name' => 'Pengembangan Produk Baru',
                'description' => 'Alur riset produk dari trial pertama sampai produksi batch awal.',
                'checkpoints' => [
                    ['Trial Resep Awal', 'trial', 'medium', 'Kembangkan beberapa alternatif resep dan dokumentasikan hasilnya.'],
                    ['Tasting Alternatif Resep', 'tasting', 'medium', 'Bandingkan alternatif resep dan pilih kandidat terbaik.'],
                    ['Revisi Resep', 'trial', 'medium', 'Perbaiki resep terpilih berdasarkan hasil tasting.'],
                    ['Quality Control', 'quality_control', 'high', 'Uji kualitas, konsistensi, dan shelf life resep final.'],
                    ['Approval Produk', 'approval', 'high', 'Ajukan resep final untuk persetujuan.'],
                    ['Produksi Batch Awal', 'production', 'high', 'Jalankan produksi batch awal dan catat yield serta kendala produksi.'],
                ],
            ],
            [
                'name' => 'Evaluasi Pasca Launching',
                'description' => 'Pemantauan performa menu setelah launching.',
                'checkpoints' => [
                    ['Evaluasi Penjualan Minggu Pertama', 'general', 'medium', 'Rangkum penjualan minggu pertama per Branch.'],
                    ['Rekap Feedback Pelanggan', 'general', 'medium', 'Kumpulkan dan rangkum feedback pelanggan dari Branch.'],
                    ['Review Kualitas Pasca Launching', 'quality_control', 'medium', 'Cek konsistensi kualitas menu setelah satu bulan berjalan.'],
                ],
            ],
        ];
    }
}
