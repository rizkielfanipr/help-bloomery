<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products added by hand to a Memo's "Product Active" summary (WIP/RAW × Store/Kitchen), next to the
 * items derived from the Menu BOMs. Kept apart from the BOM materials so a Menu refresh, which
 * rebuilds materials, never removes them. Identity is BLSS (Product ID + Product Detail ID).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_internal_memo_extra_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rnd_internal_memo_id')->constrained('rnd_internal_memos')->cascadeOnDelete();
            $table->string('scope', 20);
            $table->string('kind', 10);
            $table->unsignedBigInteger('esb_product_id');
            $table->unsignedBigInteger('esb_product_detail_id');
            $table->string('product_code')->nullable();
            $table->string('product_name');
            $table->string('uom_name', 100);
            $table->string('category_name')->nullable();
            $table->unsignedBigInteger('purchase_uom_id')->nullable();
            $table->string('purchase_uom_name')->nullable();
            $table->decimal('minimum_order', 18, 4)->nullable();
            $table->json('product_detail_snapshot')->nullable();
            $table->timestamp('product_synced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['rnd_internal_memo_id', 'scope', 'esb_product_detail_id'], 'rnd_memo_extra_products_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_internal_memo_extra_products');
    }
};
