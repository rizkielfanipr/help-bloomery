<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/rnd-internal-memo-multi-branch-prd.md §9.2 ("Tambahkan company_code pada snapshot Menu
     * jika belum tersedia") + §7.3-7.4: a Menu is merged/identified by `company_code + menuID`, and
     * BOM/Product resolution must use the Company Code the Menu actually came from, independent of
     * whichever branch context the catalog happened to be fetched through.
     *
     * Backfilled from the parent Memo's own company_code for existing rows — every Menu existing
     * today was in fact sourced from its Memo's single Company Code (there was no other option),
     * so this is a straight copy, not a guess.
     *
     * Left nullable at the schema level deliberately: tightening to NOT NULL after backfill would
     * need `->change()`, which requires doctrine/dbal (not installed, and adding a dependency for
     * this needs separate approval). NOT NULL is enforced at the application layer instead (model
     * fillable + Action validation always set it).
     */
    public function up(): void
    {
        Schema::table('rnd_internal_memo_menus', function (Blueprint $table): void {
            $table->string('company_code', 10)->nullable()->after('rnd_internal_memo_id');
        });

        // Correlated subquery (not a JOIN-UPDATE) so this runs unchanged on both MySQL production
        // and SQLite's :memory: test database.
        DB::statement('
            UPDATE rnd_internal_memo_menus
            SET company_code = (
                SELECT rnd_internal_memos.company_code
                FROM rnd_internal_memos
                WHERE rnd_internal_memos.id = rnd_internal_memo_menus.rnd_internal_memo_id
            )
        ');

        Schema::table('rnd_internal_memo_menus', function (Blueprint $table): void {
            $table->index(['company_code', 'esb_menu_id']);
        });
    }

    public function down(): void
    {
        Schema::table('rnd_internal_memo_menus', function (Blueprint $table): void {
            $table->dropIndex(['company_code', 'esb_menu_id']);
            $table->dropColumn('company_code');
        });
    }
};
