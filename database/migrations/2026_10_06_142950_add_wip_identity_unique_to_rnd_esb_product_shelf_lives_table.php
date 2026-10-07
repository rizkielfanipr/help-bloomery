<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WIP Shelf Life identity (docs/rnd-wip-shelf-life-prd.md §12.2, §21.5): one row per
 * `company_code + esb_product_detail_id`. Legacy Menu-only rows keep a NULL Product Detail ID and
 * are not affected (NULLs never collide in a unique index). The index covers soft-deleted rows too,
 * so creating an identity that was deleted restores the old row instead of inserting a new one.
 *
 * DDL only — conflicting data is never cleaned here. Run `php artisan rnd:audit-wip-shelf-life`
 * (dry-run) and resolve conflicts through an approved step first.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $conflicts = DB::table('rnd_esb_product_shelf_lives')
            ->select('company_code', 'esb_product_detail_id')
            ->whereNotNull('esb_product_detail_id')
            ->groupBy('company_code', 'esb_product_detail_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($conflicts > 0) {
            throw new RuntimeException("{$conflicts} duplicate company + Product Detail ID identities found in rnd_esb_product_shelf_lives. Run `php artisan rnd:audit-wip-shelf-life` and resolve them before migrating.");
        }

        Schema::table('rnd_esb_product_shelf_lives', function (Blueprint $table): void {
            $table->unique(['company_code', 'esb_product_detail_id'], 'rnd_shelf_life_company_product_detail_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rnd_esb_product_shelf_lives', function (Blueprint $table): void {
            $table->dropUnique('rnd_shelf_life_company_product_detail_unique');
        });
    }
};
