<?php

namespace Database\Seeders;

use App\Models\RndProject;
use App\Models\RndProjectBom;
use App\Models\SalesRegion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RndCompleteBomDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $creatorId = User::role('SUPERADMIN')->value('id') ?? User::query()->value('id');
            $project = RndProject::query()->updateOrCreate(
                ['name' => '[DEMO] Product Launch dengan BOM Lengkap'],
                [
                    'description' => 'Project dummy untuk menguji struktur BOM per Main Recipe dan export PDF.',
                    'start_date' => '2026-08-01',
                    'end_date' => '2026-10-31',
                    'created_by' => $creatorId,
                ],
            );
            $product = $project->products()->updateOrCreate(
                ['product_code' => 'DEMO-BOM-001'],
                [
                    'name' => 'Bloomery Celebration Collection',
                    'description' => 'Produk dummy dengan tiga kelompok Main Recipe lengkap.',
                    'offline_price' => 150000,
                    'online_price' => 165000,
                    'release_date' => '2026-10-01',
                    'status' => 'development',
                    'created_by' => $creatorId,
                ],
            );
            foreach (SalesRegion::query()->where('is_active', true)->get() as $region) {
                $product->regionalPrices()->updateOrCreate(
                    ['sales_region_id' => $region->id, 'effective_from' => '2026-08-01'],
                    [
                        'offline_price' => 150000 + ($region->sort_order * 5000),
                        'online_price' => 165000 + ($region->sort_order * 5000),
                        'status' => 'active',
                        'created_by' => $creatorId,
                    ],
                );
            }

            $groups = [
                1 => ['Celebration Cake', 'CAKE', [
                    'component' => ['Vanilla Sponge', 'CMP-SPONGE', [['BBMK101', 'Tepung Terigu', 'GR', 500], ['BBMK102', 'Telur', 'PCS', 8], ['BBMK103', 'Gula Pasir', 'GR', 300]]],
                    'packaging' => ['Cake Packaging', 'PKG-CAKE', [['PKG101', 'Cake Box 20 cm', 'PCS', 1], ['PKG102', 'Cake Board', 'PCS', 1], ['PKG103', 'Brand Sticker', 'PCS', 1]]],
                    'supporting' => ['Cake Support', 'SUP-CAKE', [['SUP101', 'Birthday Candle', 'PCS', 1], ['SUP102', 'Greeting Card', 'PCS', 1]]],
                ]],
                2 => ['Premium Cookies', 'COOKIE', [
                    'component' => ['Butter Cookie Dough', 'CMP-COOKIE', [['BBMK201', 'Butter', 'GR', 250], ['BBMK202', 'Tepung Protein Rendah', 'GR', 400], ['BBMK203', 'Vanilla Extract', 'ML', 5]]],
                    'packaging' => ['Cookie Packaging', 'PKG-COOKIE', [['PKG201', 'Premium Jar', 'PCS', 1], ['PKG202', 'Seal Aluminium', 'PCS', 1], ['PKG203', 'Product Label', 'PCS', 1]]],
                    'supporting' => ['Cookie Support', 'SUP-COOKIE', [['SUP201', 'Silica Gel Food Grade', 'PCS', 1], ['SUP202', 'Ribbon', 'PCS', 1]]],
                ]],
                3 => ['Signature Beverage', 'BEV', [
                    'component' => ['Strawberry Syrup', 'CMP-BEV', [['BBMK301', 'Strawberry Puree', 'ML', 80], ['BBMK302', 'Simple Syrup', 'ML', 20], ['BBMK303', 'Fresh Milk', 'ML', 150]]],
                    'packaging' => ['Beverage Packaging', 'PKG-BEV', [['PKG301', 'PET Cup 16 oz', 'PCS', 1], ['PKG302', 'Flat Lid', 'PCS', 1], ['PKG303', 'Cup Sticker', 'PCS', 1]]],
                    'supporting' => ['Beverage Support', 'SUP-BEV', [['SUP301', 'Paper Straw', 'PCS', 1], ['SUP302', 'Cup Sleeve', 'PCS', 1]]],
                ]],
            ];

            foreach ($groups as $groupNumber => [$mainName, $prefix, $children]) {
                $mainId = 990000 + ($groupNumber * 100);
                $main = $this->upsertBom($project->id, $mainId, "DEMO-$prefix-MAIN", "$mainName Main Recipe", $mainName, [
                    ["$prefix-001", "$mainName Base", 'GR', 500],
                    ["$prefix-002", "$mainName Finishing", 'GR', 100],
                    ["$prefix-003", "$mainName Decoration", 'GR', 50],
                ], $creatorId);
                $product->boms()->syncWithoutDetaching([$main->id => [
                    'usage_type' => 'main',
                    'parent_rnd_project_bom_id' => null,
                ]]);

                $childIndex = 1;
                foreach ($children as $usageType => [$childName, $childCode, $materials]) {
                    $child = $this->upsertBom(
                        $project->id,
                        $mainId + $childIndex,
                        "DEMO-$childCode",
                        $childName,
                        $childName,
                        $materials,
                        $creatorId,
                    );
                    $product->boms()->syncWithoutDetaching([$child->id => [
                        'usage_type' => $usageType,
                        'parent_rnd_project_bom_id' => $main->id,
                    ]]);
                    $childIndex++;
                }
            }
        });

        $this->command?->info('Project demo R&D dengan 3 Main Recipe dan 9 BOM turunan berhasil dibuat.');
    }

    private function upsertBom(
        int $projectId,
        int $esbId,
        string $code,
        string $name,
        string $productName,
        array $materials,
        ?int $creatorId,
    ): RndProjectBom {
        $detail = [
            'bomID' => $esbId,
            'bomTypeID' => 1,
            'bomTypeName' => 'Assembly',
            'bomCode' => $code,
            'bomName' => $name,
            'productName' => $productName,
            'uomName' => 'PCS',
            'notes' => 'Data dummy untuk pengujian export PDF.',
            'bomDetails' => collect($materials)->map(fn (array $item, int $index): array => [
                'ID' => $index + 1,
                'productCode' => $item[0],
                'productName' => $item[1],
                'uomName' => $item[2],
                'qty' => $item[3],
                'yieldPercent' => 0,
                'printGroup' => '',
            ])->all(),
        ];

        return RndProjectBom::query()->updateOrCreate(
            ['esb_bom_id' => $esbId],
            [
                'rnd_project_id' => $projectId,
                'bom_code' => $code,
                'bom_name' => $name,
                'product_name' => $productName,
                'uom_name' => 'PCS',
                'bom_type_name' => 'Assembly',
                'is_active' => true,
                'sync_status' => 'snapshot',
                'detail_snapshot' => $detail,
                'created_by' => $creatorId,
                'last_synced_at' => now(),
            ],
        );
    }
}
