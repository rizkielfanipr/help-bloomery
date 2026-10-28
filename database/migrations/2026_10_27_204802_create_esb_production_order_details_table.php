<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/products/kitchen/production-schedule/prd.md §29.2: detail rows of one snapshot header. Materials are
 * a JSON list per detail because they are only read in the detail dialog (decision K6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esb_production_order_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('esb_production_order_id')->constrained()->cascadeOnDelete();
            $table->string('external_detail_id', 100);
            $table->string('bom_id', 100)->nullable();
            $table->string('bom_name')->nullable();
            $table->string('bom_type_name')->nullable();
            $table->string('product_detail_id', 100)->nullable();
            $table->string('product_code', 100)->nullable();
            $table->string('product_name')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->string('uom_name', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_finished')->nullable();
            $table->json('materials')->nullable();
            $table->unsignedInteger('sequence');
            $table->timestamps();

            $table->unique(['esb_production_order_id', 'external_detail_id'], 'esb_po_details_order_external_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esb_production_order_details');
    }
};
