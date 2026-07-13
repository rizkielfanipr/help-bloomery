<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * @var array<string, array<int, array{code: string, name: string}>>
     *                                                                   comcode => branches
     */
    private array $data = [
        'BLO7' => [
            ['code' => 'BPB', 'name' => 'Bloomery Balcos'],
            ['code' => 'BPTMS', 'name' => 'Bloomery Tamansiswa'],
        ],
        'BLO6' => [
            ['code' => 'BPKLU', 'name' => 'Bloomery Kaliurang'],
        ],
        'BLO18' => [
            ['code' => 'BLMGS', 'name' => 'Bloomery Gading Serpong'],
        ],
        'BLO16' => [
            ['code' => 'BMS', 'name' => 'Bloomery Joymart Keprabon'],
            ['code' => 'BPL', 'name' => 'Bloomery Patisserie Pabelan'],
        ],
        'BLO15' => [
            ['code' => 'BMKL', 'name' => 'Bloomery Kota Lama'],
        ],
        'BLO14' => [
            ['code' => 'BJQC', 'name' => 'Bloomery Queen City Mall'],
        ],
        'BLO13' => [
            ['code' => 'BLPS', 'name' => 'Bloomery Surabaya'],
        ],
        'BLO12' => [
            ['code' => 'BLPJM', 'name' => 'Bloomery Joy-Mart Tugu'],
            ['code' => 'EGG', 'name' => 'Eggish'],
        ],
        'BLO11' => [
            ['code' => 'BLOBM', 'name' => 'Bloomery Blok M'],
        ],
        'BLO10' => [
            ['code' => 'BPST', 'name' => 'Bloomery Tembalang'],
            ['code' => 'ISBH', 'name' => 'Istana Buah'],
        ],
        'BLMN' => [
            ['code' => 'HJNW', 'name' => 'Haji Nawi'],
            ['code' => 'BLOPG', 'name' => 'Bloomery Pesanggrahan'],
        ],
        'BLAR' => [
            ['code' => 'BLA', 'name' => 'Bloomery Atelier'],
        ],
    ];

    public function run(): void
    {
        foreach ($this->data as $comcode => $branches) {
            foreach ($branches as $branch) {
                $record = Branch::firstOrCreate(
                    ['esb_branch_code' => $branch['code']],
                    ['name' => $branch['name'], 'is_active' => true]
                );

                $record->update([
                    'name' => $branch['name'],
                    'esb_comcode' => $comcode,
                ]);

                $this->command->info("{$branch['code']} ({$comcode}): {$branch['name']}");
            }
        }
    }
}
