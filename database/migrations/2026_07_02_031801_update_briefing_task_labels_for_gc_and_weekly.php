<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, array{label: string, sort_order?: int}> */
    private array $updates = [
        // Monthly General Cleaning
        'monthly_cleaning_chiller' => ['label' => 'Mencuci semua komponen showcase chiller', 'sort_order' => 2],
        'monthly_cleaning_docs' => ['label' => 'Membersihkan filter showcase display', 'sort_order' => 3],
        'monthly_cleaning_floor' => ['label' => 'Membersihkan lantai dan dinding dari spot hitam (sesuai SOP, lantai kayu tidak boleh basah)', 'sort_order' => 4],
        'monthly_cleaning_freezer' => ['label' => 'Menguras bunga es freezer', 'sort_order' => 5],
        'monthly_cleaning_sink' => ['label' => 'Membersihkan sink & wastafel dengan Polki / Kifa pengkilap', 'sort_order' => 6],
        'monthly_cleaning_equipment' => ['label' => 'Membersihkan dispenser, kompor, blender, atau equipment lain yang dipunyai (Deep cleaning)', 'sort_order' => 7],
        'monthly_cleaning_coffee' => ['label' => 'Deep cleaning mesin kopi menggunakan puro', 'sort_order' => 8],
        'monthly_cleaning_sofa' => ['label' => 'Vacuum sofa', 'sort_order' => 9],
        'monthly_cleaning_discard' => ['label' => 'Mengumpulkan barang yang perlu diloak / tidak terpakai', 'sort_order' => 10],
        'monthly_cleaning_specific' => ['label' => 'Deep cleaning item spesifik di store tersebut', 'sort_order' => 11],

        // Weekly Cleaning
        'weekly_cleaning_kondensor' => ['label' => 'Membersihkan filter kondensor showcase display (sesuai SOP)'],
        'weekly_cleaning_mesin_kopi' => ['label' => 'Membersihkan mesin kopi menggunakan Puro'],
        'weekly_cleaning_genset' => ['label' => 'Memanaskan genset 15 menit'],
        'weekly_cleaning_tempat_sampah' => ['label' => 'Mencuci tempat sampah & keset'],
        'weekly_cleaning_showcase_chiller' => ['label' => 'Membersihkan showcase Chiller inventory (karet, rak & atas unit)'],
    ];

    private array $branchSuffixes = ['_2', '_2_2'];

    public function up(): void
    {
        foreach ($this->updates as $baseKey => $data) {
            $keys = array_merge([$baseKey], array_map(fn ($s) => $baseKey.$s, $this->branchSuffixes));

            $payload = ['label' => $data['label'], 'updated_at' => now()];
            if (isset($data['sort_order'])) {
                $payload['sort_order'] = $data['sort_order'];
            }

            DB::table('briefing_tasks')->whereIn('key', $keys)->update($payload);
        }
    }

    public function down(): void
    {
        $originals = [
            'monthly_cleaning_chiller' => ['label' => 'Cuci Komponen Showcase Chiller & Display', 'sort_order' => 2],
            'monthly_cleaning_docs' => ['label' => 'Rapikan File Sales & Surat Jalan', 'sort_order' => 10],
            'monthly_cleaning_floor' => ['label' => 'Bersihkan Lantai & Dinding dari Spot Hitam', 'sort_order' => 3],
            'monthly_cleaning_freezer' => ['label' => 'Kuras Bunga Es Freezer', 'sort_order' => 4],
            'monthly_cleaning_sink' => ['label' => 'Poles Sink & Wastafel', 'sort_order' => 5],
            'monthly_cleaning_equipment' => ['label' => 'Deep Cleaning Dispenser, Kompor & Blender', 'sort_order' => 6],
            'monthly_cleaning_coffee' => ['label' => 'Deep Cleaning Mesin Kopi', 'sort_order' => 7],
            'monthly_cleaning_sofa' => ['label' => 'Vacuum Sofa', 'sort_order' => 8],
            'monthly_cleaning_discard' => ['label' => 'Sortir & Singkirkan Barang Tidak Terpakai', 'sort_order' => 9],
            'monthly_cleaning_specific' => ['label' => 'Deep Cleaning Item Spesifik Store', 'sort_order' => 11],
            'weekly_cleaning_kondensor' => ['label' => 'Membersihkan filter kondensor area showcase display sesuai dengan SOP'],
            'weekly_cleaning_mesin_kopi' => ['label' => 'Membersihkan area mesin kopi menggunakan Puro'],
            'weekly_cleaning_genset' => ['label' => 'Memanaskan genset 15 menit'],
            'weekly_cleaning_tempat_sampah' => ['label' => 'Mencuci tempat sampah & keset'],
            'weekly_cleaning_showcase_chiller' => ['label' => 'Membersihkan showcase Chiller inventory (bagian karet, rak, dan atas unit)'],
        ];

        foreach ($originals as $baseKey => $data) {
            $keys = array_merge([$baseKey], array_map(fn ($s) => $baseKey.$s, $this->branchSuffixes));

            $payload = ['label' => $data['label'], 'updated_at' => now()];
            if (isset($data['sort_order'])) {
                $payload['sort_order'] = $data['sort_order'];
            }

            DB::table('briefing_tasks')->whereIn('key', $keys)->update($payload);
        }
    }
};
