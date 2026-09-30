<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 3 of docs/rnd-internal-memo-simplification-prd.md. `product_snapshot` already stores
     * the raw BOM component from InternalMemoBomResolver (docs/rnd-internal-memo-prd.md §12.3) —
     * this is a distinct raw response from the Product/Purchase-UOM lookup so the two never
     * overwrite each other.
     */
    public function up(): void
    {
        Schema::table('rnd_internal_memo_materials', function (Blueprint $table): void {
            $table->json('product_detail_snapshot')->nullable()->after('product_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rnd_internal_memo_materials', function (Blueprint $table): void {
            $table->dropColumn('product_detail_snapshot');
        });
    }
};
