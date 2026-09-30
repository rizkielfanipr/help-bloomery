<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 1 of docs/rnd-internal-memo-simplification-prd.md §10.3. Additive-only: the Phase 0
     * audit proved no ESB Product contract field represents "Purchase UOM" (only `unit`,
     * `baseUnit`, and `conversionFactor` exist on a product detail), so `purchase_uom_id`/
     * `purchase_uom_name` stay nullable and are only ever populated once that contract is proven
     * per Menu/product — a null value means "Purchase UOM belum tersedia", not "not yet fetched".
     */
    public function up(): void
    {
        Schema::table('rnd_internal_memo_materials', function (Blueprint $table): void {
            $table->unsignedBigInteger('purchase_uom_id')->nullable()->after('uom_name');
            $table->string('purchase_uom_name')->nullable()->after('purchase_uom_id');
            $table->decimal('minimum_order', 18, 4)->nullable()->after('net_quantity');
            $table->timestamp('product_synced_at')->nullable()->after('product_snapshot');

            $table->index('product_code', 'rnd_memo_materials_product_code_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rnd_internal_memo_materials', function (Blueprint $table): void {
            $table->dropIndex('rnd_memo_materials_product_code_idx');
            $table->dropColumn(['purchase_uom_id', 'purchase_uom_name', 'minimum_order', 'product_synced_at']);
        });
    }
};
