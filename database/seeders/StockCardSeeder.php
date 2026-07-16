<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\StockCard;
use App\Models\StockCardEntry;
use App\Models\User;
use App\Services\EsbService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class StockCardSeeder extends Seeder
{
    private const FAKE_MATERIALS = [
        ['code' => 'MAT-001', 'name' => 'Ayam Fillet',       'unit' => 'KG',   'base' => 8.0],
        ['code' => 'MAT-002', 'name' => 'Telur Ayam',        'unit' => 'Butir', 'base' => 120.0],
        ['code' => 'MAT-003', 'name' => 'Tepung Terigu',     'unit' => 'KG',   'base' => 25.0],
        ['code' => 'MAT-004', 'name' => 'Gula Pasir',        'unit' => 'KG',   'base' => 10.0],
        ['code' => 'MAT-005', 'name' => 'Mentega Tawar',     'unit' => 'KG',   'base' => 6.0],
        ['code' => 'MAT-006', 'name' => 'Susu Cair Full Fat', 'unit' => 'Liter', 'base' => 15.0],
        ['code' => 'MAT-007', 'name' => 'Keju Cheddar',      'unit' => 'KG',   'base' => 3.0],
        ['code' => 'MAT-008', 'name' => 'Cokelat Couverture', 'unit' => 'KG',   'base' => 4.0],
        ['code' => 'MAT-009', 'name' => 'Krim Kental',       'unit' => 'Liter', 'base' => 8.0],
        ['code' => 'MAT-010', 'name' => 'Ragi Instant',      'unit' => 'GR',   'base' => 200.0],
        ['code' => 'MAT-011', 'name' => 'Garam Halus',       'unit' => 'GR',   'base' => 300.0],
        ['code' => 'MAT-012', 'name' => 'Strawberry Segar',  'unit' => 'KG',   'base' => 5.0],
        ['code' => 'MAT-013', 'name' => 'Blueberry Import',  'unit' => 'KG',   'base' => 2.0],
        ['code' => 'MAT-014', 'name' => 'Mangga Arum Manis', 'unit' => 'KG',   'base' => 6.0],
        ['code' => 'MAT-015', 'name' => 'Vanilla Ekstrak',   'unit' => 'ML',   'base' => 500.0],
    ];

    private const DEFICIT_NOTES = [
        'Tercecer saat pemindahan',
        'Tumpah saat pemakaian',
        'Kualitas tidak layak, dibuang',
        'Selisih timbangan bahan baku',
        'Kesalahan hitung stok awal',
        'Digunakan untuk sampling produk',
        'Penguapan saat penyimpanan',
    ];

    private const SURPLUS_NOTES = [
        'Stok lama belum terpakai',
        'Pengiriman lebih dari order',
        'Salah input sistem sebelumnya',
        'Retur bahan dari kitchen',
    ];

    public function run(): void
    {
        StockCardEntry::query()->delete();
        StockCard::query()->delete();

        $esb = app(EsbService::class);
        $useEsb = $esb->isConfigured();

        $branches = Branch::whereNotNull('esb_branch_code')
            ->whereNotNull('esb_comcode')
            ->get();

        if ($branches->isEmpty()) {
            $this->command->warn('No branches with esb_branch_code found, skipping.');

            return;
        }

        $submittedBy = User::first()?->id;

        $dates = collect();
        for ($i = 13; $i >= 0; $i--) {
            $dates->push(Carbon::today()->subDays($i)->format('Y-m-d'));
        }

        foreach ($branches as $branch) {
            $this->command->info("Seeding branch: {$branch->name} ({$branch->esb_branch_code})");

            foreach ($dates as $date) {
                $materials = $this->fetchMaterials($esb, $useEsb, $branch, $date);

                if (empty($materials)) {
                    $this->command->line("  [$date] No data, skipping.");

                    continue;
                }

                $card = StockCard::create([
                    'branch_id' => $branch->id,
                    'submitted_by' => $submittedBy,
                    'report_date' => $date,
                    'flag_unit' => 'stockUnit',
                    'submitted_at' => Carbon::parse($date)->setTime(fake()->numberBetween(16, 19), fake()->numberBetween(0, 59)),
                ]);

                foreach ($materials as $mat) {
                    $systemQty = (float) $mat['qty'];

                    // ~60% exact, ~25% deficit, ~15% surplus
                    $roll = fake()->numberBetween(1, 100);
                    if ($roll <= 60) {
                        $actualQty = $systemQty;
                        $notes = null;
                    } elseif ($roll <= 85) {
                        $delta = round($systemQty * fake()->randomFloat(2, 0.01, 0.10), 4);
                        $actualQty = max(0, $systemQty - $delta);
                        $notes = fake()->randomElement(self::DEFICIT_NOTES);
                    } else {
                        $delta = round($systemQty * fake()->randomFloat(2, 0.01, 0.05), 4);
                        $actualQty = $systemQty + $delta;
                        $notes = fake()->randomElement(self::SURPLUS_NOTES);
                    }

                    StockCardEntry::create([
                        'stock_card_id' => $card->id,
                        'product_code' => $mat['code'],
                        'product_name' => $mat['name'],
                        'system_qty' => $systemQty,
                        'system_unit' => $mat['unit'],
                        'actual_qty' => $actualQty,
                        'notes' => $notes,
                    ]);
                }

                $this->command->info("  [$date] Seeded ".count($materials).' entries.');
            }
        }
    }

    private function fetchMaterials(EsbService $esb, bool $useEsb, Branch $branch, string $date): array
    {
        if ($useEsb && $branch->esb_token) {
            try {
                $rows = $esb->getDailySalesMaterialUsage($branch->esb_branch_code, $date, 'stockUnit', $branch->esb_token);

                return array_map(fn ($r) => [
                    'code' => $r['productCode'] ?? '',
                    'name' => $r['productName'] ?? '',
                    'qty' => (float) ($r['totalQty'] ?? 0),
                    'unit' => $r['unit'] ?? 'PCS',
                ], $rows);
            } catch (\RuntimeException) {
                // fall through to fake data
            }
        }

        // Fake data: randomise qty slightly per date/branch so each day looks different
        $seed = crc32($branch->esb_branch_code.$date);
        mt_srand($seed);

        return array_map(fn ($mat) => [
            'code' => $mat['code'],
            'name' => $mat['name'],
            'unit' => $mat['unit'],
            'qty' => round($mat['base'] * (0.7 + (mt_rand(0, 600) / 1000)), 4),
        ], self::FAKE_MATERIALS);
    }
}
