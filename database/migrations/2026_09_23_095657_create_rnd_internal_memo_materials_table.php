<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Simplified from docs/rnd-internal-memo-prd.md §12.3 on explicit user instruction: waste,
     * tolerance, and gross quantity are dropped because no ESB BOM contract evidence proves
     * those fields exist (see the Phase 0 contract report). Only the material and its quantity
     * are used, matching the already-proven production algorithm in
     * App\Services\RndProjectMaterialForecastService (qty x parent requirement, no yield
     * division).
     */
    public function up(): void
    {
        Schema::create('rnd_internal_memo_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_internal_memo_menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_material_id')->nullable()->constrained('rnd_internal_memo_materials')->cascadeOnDelete();
            $table->unsignedBigInteger('source_bom_id');
            $table->string('source_bom_code')->nullable();
            $table->json('source_path');
            $table->unsignedInteger('depth')->default(0);
            $table->unsignedBigInteger('esb_product_id')->nullable();
            $table->unsignedBigInteger('esb_product_detail_id')->nullable();
            $table->string('product_code')->nullable();
            $table->string('product_name');
            $table->string('category_name')->nullable();
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->string('uom_name');
            $table->decimal('quantity_per_menu', 18, 4);
            $table->decimal('net_quantity', 18, 4);
            $table->boolean('is_wip')->default(false);
            $table->boolean('is_packaging')->default(false);
            $table->json('product_snapshot')->nullable();
            $table->timestamps();

            $table->index(['rnd_internal_memo_menu_id', 'depth'], 'rnd_memo_materials_menu_depth_idx');
            $table->index(['esb_product_detail_id'], 'rnd_memo_materials_product_detail_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_internal_memo_materials');
    }
};
