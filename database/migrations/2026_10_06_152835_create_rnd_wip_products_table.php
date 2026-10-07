<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local read model of the active ESB Products in category "Barang WIP", one row per Product Detail
 * (unit). The Shelf Life menu lists the base unit row of each Product; the other unit rows only map
 * a BOM's non-base Product Detail ID back to that Product's master. It only lists WIPs; the master itself stays in
 * `rnd_esb_product_shelf_lives`, so re-syncing this table never touches Shelf Life data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_wip_products', function (Blueprint $table) {
            $table->id();
            $table->string('company_code', 20);
            $table->unsignedBigInteger('esb_product_id')->nullable();
            $table->unsignedBigInteger('product_detail_id');
            $table->string('product_code')->nullable();
            $table->string('product_name');
            $table->string('uom_name', 100)->nullable();
            $table->boolean('is_base')->default(true);
            $table->string('category_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['company_code', 'product_detail_id'], 'rnd_wip_products_company_detail_unique');
            $table->index(['is_active', 'is_base', 'product_name']);
            $table->index('esb_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_wip_products');
    }
};
