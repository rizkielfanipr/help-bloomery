<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\QualityControlAudit;
use App\Models\QualityControlChecklistItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class QualityControlAuditSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(QualityControlChecklistSeeder::class);

        $auditor = User::where('email', 'qc@bloomery.test')->first();
        $branches = Branch::where('is_active', true)->orderBy('id')->get();

        if (! $auditor || $branches->isEmpty()) {
            $this->command->warn('Melewati QualityControlAuditSeeder: user qc@bloomery.test atau branch aktif belum tersedia.');

            return;
        }

        $checklist = QualityControlChecklistItem::where('is_active', true)->orderBy('sort_order')->get();

        $audits = [
            [
                'branch' => 0,
                'days_ago' => 21,
                'audit_type' => 'routine',
                'store_leader_name' => 'Dewi Anggraini',
                'store_leader_present' => true,
                'status' => 'submitted',
                'fails' => [
                    'Sanitizer solution available & correct concentration' => 'Botol sanitizer di area packing kosong, belum diisi ulang saat kunjungan.',
                ],
                'top_findings' => "1. Sanitizer packing kosong.\n2. Tidak ada temuan besar lainnya.\n3. Store secara umum rapi dan sesuai SOP.",
                'corrective_action_required' => 'Isi ulang sanitizer packing dan tambahkan checklist harian pengecekan.',
                'overall_notes' => 'Kondisi store baik, hanya perlu perhatian pada ketersediaan sanitizer.',
            ],
            [
                'branch' => 1,
                'days_ago' => 17,
                'audit_type' => 'routine',
                'store_leader_name' => 'Bagus Prasetyo',
                'store_leader_present' => true,
                'status' => 'submitted',
                'fails' => [
                    'FIFO/FEFO implemented correctly' => 'Ditemukan 3 produk lama tertumpuk di belakang produk baru pada rak display.',
                    'Chiller temperature within range + log filled' => 'Temperature log tidak diisi sejak 2 hari terakhir.',
                    'Product label complete (name, date, batch)' => 'Beberapa produk savory belum mencantumkan tanggal produksi.',
                    'Data produk off dan alasannya' => 'Catatan produk off tidak sesuai dengan kondisi stok aktual di sistem.',
                    'Service time test (5 random transactions)' => 'Rata-rata waktu layanan 14 menit, melebihi standar 10 menit.',
                ],
                'top_findings' => "1. FIFO/FEFO belum konsisten di rak display.\n2. Temperature log chiller tidak terisi.\n3. Waktu layanan transaksi melebihi standar.",
                'corrective_action_required' => 'Retraining FIFO/FEFO untuk seluruh staff shift, dan pengawasan pengisian temperature log oleh store leader.',
                'overall_notes' => 'Perlu follow up audit dalam 2 minggu untuk memastikan perbaikan berjalan.',
            ],
            [
                'branch' => 0,
                'days_ago' => 10,
                'audit_type' => 'surprise',
                'store_leader_name' => 'Dewi Anggraini',
                'store_leader_present' => false,
                'status' => 'submitted',
                'fails' => [
                    'Chiller out-of-range without corrective action?' => 'Suhu chiller display tercatat 9°C selama lebih dari 3 jam tanpa tindakan, produk savory berisiko rusak.',
                    'Expired product found?' => 'Ditemukan 2 produk kedaluwarsa masih dipajang di etalase depan.',
                    'Pest sign evidence?' => 'Ditemukan jejak semut di sekitar area penyimpanan gula.',
                    'Kebersihan dan kerapian toko secara keseluruhan' => 'Area belakang toko berantakan, dus dan galon tidak tertata rapi.',
                    'Cash discrepancy without report?' => 'Selisih kas Rp75.000 pada laporan setoran kemarin belum dilaporkan ke Finance.',
                    'Staff hygiene complete (hairnet/apron/gloves)' => '2 dari 4 staff tidak menggunakan hairnet saat observasi.',
                    'Abnormal transaction report reviewed by leader' => 'Tidak ada review transaksi mingguan oleh store leader.',
                ],
                'top_findings' => "1. Produk kedaluwarsa masih dipajang.\n2. Suhu chiller di luar rentang tanpa tindakan.\n3. Selisih kas belum dilaporkan.",
                'corrective_action_required' => 'Store leader wajib menarik seluruh produk kedaluwarsa hari ini juga, perbaikan chiller dijadwalkan maintenance, dan laporan selisih kas dikirim ke Finance maksimal H+1.',
                'overall_notes' => 'Surprise audit menemukan beberapa pelanggaran kritikal. Perlu tindak lanjut ketat dan re-audit dalam 1 minggu.',
            ],
            [
                'branch' => 1,
                'days_ago' => 5,
                'audit_type' => 'follow_up',
                'store_leader_name' => 'Bagus Prasetyo',
                'store_leader_present' => true,
                'status' => 'submitted',
                'fails' => [
                    'Online service time test (5 random checks)' => 'Waktu balas chat admin rata-rata 7 menit, sedikit di atas standar 5 menit.',
                ],
                'top_findings' => "1. Waktu respon chat online sedikit melambat.\n2. Perbaikan FIFO dari audit sebelumnya sudah berjalan baik.",
                'corrective_action_required' => 'Tambahkan reminder shift untuk admin online agar responsif dalam 5 menit.',
                'overall_notes' => 'Follow up audit menunjukkan perbaikan signifikan dibanding audit sebelumnya.',
            ],
            [
                'branch' => 0,
                'days_ago' => 2,
                'audit_type' => 'routine',
                'store_leader_name' => 'Dewi Anggraini',
                'store_leader_present' => true,
                'status' => 'draft',
                'fails' => [
                    'Toilet & sink cleanliness' => 'Sink cuci piring tersumbat ringan, air tergenang.',
                ],
                'partial' => 20,
            ],
        ];

        foreach ($audits as $data) {
            $branch = $branches[$data['branch']] ?? $branches->first();

            $audit = QualityControlAudit::create([
                'branch_id' => $branch->id,
                'auditor_id' => $auditor->id,
                'audit_date' => Carbon::today()->subDays($data['days_ago']),
                'audit_type' => $data['audit_type'],
                'store_leader_name' => $data['store_leader_name'],
                'store_leader_present' => $data['store_leader_present'],
                'status' => 'draft',
            ]);

            foreach ($checklist as $index => $item) {
                $audit->items()->create([
                    'quality_control_checklist_item_id' => $item->id,
                    'section_code' => $item->section_code,
                    'section_name' => $item->section_name,
                    'question' => $item->question,
                    'check_procedure' => $item->check_procedure,
                    'maximum_points' => $item->points,
                    'is_critical' => $item->is_critical,
                    'requires_photo' => $item->requires_photo,
                    'sort_order' => $item->sort_order,
                ]);
            }

            $items = $audit->items()->orderBy('sort_order')->get();
            $answeredCount = $data['partial'] ?? $items->count();

            foreach ($items->take($answeredCount) as $item) {
                if ($item->maximum_points === 0) {
                    $item->update(['result' => 'not_applicable']);

                    continue;
                }

                $fail = $data['fails'][$item->question] ?? null;

                if ($fail) {
                    $item->update([
                        'result' => 'fail',
                        'notes' => $fail,
                        'corrective_action' => $data['status'] === 'draft'
                            ? null
                            : 'Ditindaklanjuti sesuai catatan corrective action audit.',
                        'action_status' => $data['status'] === 'draft' ? 'open' : 'in_progress',
                    ]);

                    continue;
                }

                $item->update(['result' => 'pass']);
            }

            if ($data['status'] === 'submitted') {
                $audit->update([
                    'status' => 'submitted',
                    'submitted_at' => $audit->audit_date->copy()->addHours(3),
                    'top_findings' => $data['top_findings'] ?? null,
                    'corrective_action_required' => $data['corrective_action_required'] ?? null,
                    'overall_notes' => $data['overall_notes'] ?? null,
                ]);
            }

            $audit->recalculateScore();
        }

        $this->command->info('Quality Control: '.count($audits).' dummy audit dibuat untuk '.$auditor->name.'.');
    }
}
