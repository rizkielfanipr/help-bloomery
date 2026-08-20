<?php

namespace Database\Seeders;

use App\Models\QualityControlChecklistItem;
use Illuminate\Database\Seeder;

class QualityControlChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            'A' => 'Hygiene & Food Safety', 'B' => 'Product Quality & Display',
            'C' => 'Service Speed & Reliability', 'D' => 'Inventory & Availability',
            'E' => 'Cash Handling & Anti-Fraud', 'F' => 'SOP Discipline & Team Readiness',
        ];

        $items = [
            ['A', 'Staff hygiene complete (hairnet/apron/gloves)', 3, 'Kelengkapan hairnet, apron, dan handgloves'],
            ['A', 'Handwashing SOP observed / compliance', 2, 'Checklist kebersihan tangan, kuku, tanpa aksesori dan perhiasan'],
            ['A', 'Sanitizer solution available & correct concentration', 2, 'Tersedia alkohol dan alat pembersih yang sesuai dan memadai'],
            ['A', 'Chiller temperature within range + log filled', 3, 'Temperature log terisi lengkap dan berada dalam rentang aman'],
            ['A', 'Chiller out-of-range without corrective action?', 10, 'Periksa aksi koreksi atau preventif terhadap suhu chiller', true],
            ['A', 'FIFO/FEFO implemented correctly', 15, 'Periksa penerapan FIFO/FEFO pada area penyimpanan dan display'],
            ['A', 'Toilet & sink cleanliness', 3, 'Periksa kebersihan toilet dan sink cuci piring'],
            ['A', 'Garbage management (closed bins, schedule)', 2, 'Tempat sampah tertutup dan jadwal pembersihan berjalan'],
            ['A', 'Pest sign evidence?', 5, 'Pastikan tidak ada semut, lalat, kecoak, tikus, atau hama lain', true],
            ['A', 'Kebersihan dan kerapian toko secara keseluruhan', 10, 'Periksa dinding, lantai, area penyimpanan, dan area packing'],
            ['A', 'Expired product found?', 15, 'Pastikan produk display bukan produk kedaluwarsa', true],
            ['A', 'Evidence photo upload (if any issue found)', 0, 'Lampirkan foto jika ditemukan masalah', false, true],
            ['B', 'Maintenance card alat berat lengkap', 3, 'Kartu genset, AC, mesin kopi, chiller, dan showcase terisi serta ditandatangani'],
            ['B', 'Product label complete (name, date, batch)', 15, 'Nama produk, tanggal produksi, kedaluwarsa, dan produsen tercantum lengkap'],
            ['B', 'Packaging readiness (box/cutlery/bag)', 3, 'Ketersediaan packaging sesuai ketentuan penggunaan'],
            ['B', 'Implementasi SOP produk sesuai', 5, 'Periksa penerapan SOP produk yang berlaku'],
            ['B', 'Evidence photo upload (display/label)', 0, 'Lampirkan foto display atau label', false, true],
            ['C', 'Cashier station readiness', 5, 'Area kasir bersih, rapi, dan siap menerima customer'],
            ['C', 'Queue management/flow', 2, 'Pastikan keramaian dan antrean terkontrol'],
            ['C', 'Service time test (5 random transactions)', 10, 'Maksimal 10 menit; savory 15 menit, kondisi ramai 30–40 menit'],
            ['C', 'Pickup order readiness (OJOL/WA process)', 3, 'Kesiapan produk OJOL/WA tidak lebih dari 15 menit'],
            ['C', 'Offline customer service', 0, 'Observasi pelayanan customer offline'],
            ['C', 'Online service time test (5 random checks)', 5, 'Waktu balas chat admin tidak lebih dari 5 menit'],
            ['C', 'Complaint store by WA', 15, 'Periksa zero complaint melalui pencarian pada WA admin'],
            ['D', 'Top 20 SKU availability check done 2x/day', 3, 'Periksa ketersediaan 20 SKU terlaris di store'],
            ['D', 'Data produk off dan alasannya', 5, 'Catatan produk off sesuai dengan stok aktual'],
            ['D', 'Expired/waste record complete', 4, 'Pencatatan waste lengkap dan sesuai kondisi aktual'],
            ['E', 'Refund/void SOP approval + evidence', 3, 'Refund mengikuti SOP dan memiliki bukti pendukung'],
            ['E', 'CCTV position OK + recording check', 2, 'Posisi CCTV tepat, menyala, dan rekaman dapat diperiksa'],
            ['E', 'Abnormal transaction report reviewed by leader', 3, 'Periksa kejanggalan transaksi dan laporan cash'],
            ['E', 'Cash discrepancy without report?', 10, 'Bandingkan cash aktual, laporan setoran, dan laporan ESB', true],
            ['F', 'Briefing log filled', 2, 'Periksa konsistensi briefing pada grup dan data Daily Briefing'],
            ['F', 'Cleaning checklist filled', 2, 'Checklist kebersihan tersedia dan terisi sampai waktu terbaru'],
            ['F', 'Staff roster matches demand', 2, 'Jumlah staff sesuai tingkat kebutuhan store'],
            ['F', 'Store leader present & can explain KPI', 3, 'Store leader hadir dan mampu menjelaskan kondisi KPI store'],
            ['F', 'Incident log + action plan template done', 5, 'Insiden tercatat, ditindaklanjuti, dan memiliki durasi penanganan'],
        ];

        foreach ($items as $index => $item) {
            [$sectionCode, $question, $points, $procedure] = $item;

            QualityControlChecklistItem::updateOrCreate(
                ['section_code' => $sectionCode, 'question' => $question],
                [
                    'section_name' => $sections[$sectionCode], 'check_procedure' => $procedure,
                    'points' => $points, 'is_critical' => $item[4] ?? false,
                    'requires_photo' => $item[5] ?? false, 'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
