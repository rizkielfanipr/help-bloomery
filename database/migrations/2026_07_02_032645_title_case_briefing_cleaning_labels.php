<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, array{new: string, old: string}> */
    private array $labels = [
        // Monthly General Cleaning
        'monthly_cleaning_chiller' => ['new' => 'Cuci Komponen Showcase Chiller', 'old' => 'Cuci komponen showcase chiller'],
        'monthly_cleaning_docs' => ['new' => 'Bersihkan Filter Showcase Display', 'old' => 'Bersihkan filter showcase display'],
        'monthly_cleaning_floor' => ['new' => 'Bersihkan Lantai & Dinding dari Spot Hitam', 'old' => 'Bersihkan lantai & dinding dari spot hitam'],
        'monthly_cleaning_freezer' => ['new' => 'Kuras Bunga Es Freezer', 'old' => 'Kuras bunga es freezer'],
        'monthly_cleaning_sink' => ['new' => 'Poles Sink & Wastafel', 'old' => 'Poles sink & wastafel'],
        'monthly_cleaning_equipment' => ['new' => 'Deep Cleaning Dispenser, Kompor & Blender', 'old' => 'Deep cleaning dispenser, kompor & blender'],
        'monthly_cleaning_coffee' => ['new' => 'Deep Cleaning Mesin Kopi', 'old' => 'Deep cleaning mesin kopi'],
        'monthly_cleaning_sofa' => ['new' => 'Vacuum Sofa', 'old' => 'Vacuum sofa'],
        'monthly_cleaning_discard' => ['new' => 'Sortir Barang Tidak Terpakai / Diloak', 'old' => 'Sortir barang tidak terpakai / diloak'],
        'monthly_cleaning_specific' => ['new' => 'Deep Cleaning Item Spesifik Store', 'old' => 'Deep cleaning item spesifik store'],

        // Weekly Cleaning
        'weekly_cleaning_kondensor' => ['new' => 'Bersihkan Filter Kondensor Showcase Display', 'old' => 'Bersihkan filter kondensor showcase display'],
        'weekly_cleaning_mesin_kopi' => ['new' => 'Bersihkan Mesin Kopi (Puro)', 'old' => 'Bersihkan mesin kopi (Puro)'],
        'weekly_cleaning_genset' => ['new' => 'Panaskan Genset 15 Menit', 'old' => 'Panaskan genset 15 menit'],
        'weekly_cleaning_tempat_sampah' => ['new' => 'Cuci Tempat Sampah & Keset', 'old' => 'Cuci tempat sampah & keset'],
        'weekly_cleaning_showcase_chiller' => ['new' => 'Bersihkan Showcase Chiller Inventory', 'old' => 'Bersihkan showcase Chiller inventory'],
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
