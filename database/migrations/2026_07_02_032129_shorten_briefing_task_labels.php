<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, array{new: string, old: string}> */
    private array $labels = [
        'monthly_cleaning_chiller' => ['new' => 'Cuci komponen showcase chiller', 'old' => 'Mencuci semua komponen showcase chiller'],
        'monthly_cleaning_docs' => ['new' => 'Bersihkan filter showcase display', 'old' => 'Membersihkan filter showcase display'],
        'monthly_cleaning_floor' => ['new' => 'Bersihkan lantai & dinding dari spot hitam', 'old' => 'Membersihkan lantai dan dinding dari spot hitam (sesuai SOP, lantai kayu tidak boleh basah)'],
        'monthly_cleaning_freezer' => ['new' => 'Kuras bunga es freezer', 'old' => 'Menguras bunga es freezer'],
        'monthly_cleaning_sink' => ['new' => 'Poles sink & wastafel', 'old' => 'Membersihkan sink & wastafel dengan Polki / Kifa pengkilap'],
        'monthly_cleaning_equipment' => ['new' => 'Deep cleaning dispenser, kompor & blender', 'old' => 'Membersihkan dispenser, kompor, blender, atau equipment lain yang dipunyai (Deep cleaning)'],
        'monthly_cleaning_coffee' => ['new' => 'Deep cleaning mesin kopi', 'old' => 'Deep cleaning mesin kopi menggunakan puro'],
        'monthly_cleaning_sofa' => ['new' => 'Vacuum sofa', 'old' => 'Vacuum sofa'],
        'monthly_cleaning_discard' => ['new' => 'Sortir barang tidak terpakai / diloak', 'old' => 'Mengumpulkan barang yang perlu diloak / tidak terpakai'],
        'monthly_cleaning_specific' => ['new' => 'Deep cleaning item spesifik store', 'old' => 'Deep cleaning item spesifik di store tersebut'],
        'weekly_cleaning_kondensor' => ['new' => 'Bersihkan filter kondensor showcase display', 'old' => 'Membersihkan filter kondensor showcase display (sesuai SOP)'],
        'weekly_cleaning_mesin_kopi' => ['new' => 'Bersihkan mesin kopi (Puro)', 'old' => 'Membersihkan mesin kopi menggunakan Puro'],
        'weekly_cleaning_genset' => ['new' => 'Panaskan genset 15 menit', 'old' => 'Memanaskan genset 15 menit'],
        'weekly_cleaning_tempat_sampah' => ['new' => 'Cuci tempat sampah & keset', 'old' => 'Mencuci tempat sampah & keset'],
        'weekly_cleaning_showcase_chiller' => ['new' => 'Bersihkan showcase Chiller inventory', 'old' => 'Membersihkan showcase Chiller inventory (karet, rak & atas unit)'],
    ];

    private array $branchSuffixes = ['_2', '_2_2'];

    public function up(): void
    {
        foreach ($this->labels as $baseKey => $data) {
            $keys = array_merge([$baseKey], array_map(fn ($s) => $baseKey.$s, $this->branchSuffixes));
            DB::table('briefing_tasks')->whereIn('key', $keys)->update(['label' => $data['new'], 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach ($this->labels as $baseKey => $data) {
            $keys = array_merge([$baseKey], array_map(fn ($s) => $baseKey.$s, $this->branchSuffixes));
            DB::table('briefing_tasks')->whereIn('key', $keys)->update(['label' => $data['old'], 'updated_at' => now()]);
        }
    }
};
