<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esb_purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('purchase_num')->unique();
            $table->dateTime('purchase_date')->nullable()->index();
            $table->dateTime('required_date')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('branch_name')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->string('supplier_name')->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->string('currency_name')->nullable();
            $table->decimal('rate', 20, 4)->default(1);
            $table->decimal('purchase_total', 20, 4)->default(0);
            $table->unsignedInteger('status_id')->nullable()->index();
            $table->string('status_name')->nullable()->index();
            $table->dateTime('esb_edited_at')->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('esb_purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('esb_purchase_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('esb_detail_id');
            $table->unsignedBigInteger('product_detail_id')->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('product_code')->nullable()->index();
            $table->string('product_name')->index();
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->string('uom_name')->nullable()->index();
            $table->decimal('qty', 20, 4)->default(0);
            $table->decimal('conversion_qty', 20, 4)->default(1);
            $table->decimal('stock_qty', 20, 4)->default(0);
            $table->decimal('pricelist_price', 20, 4)->default(0);
            $table->decimal('price', 20, 4)->default(0);
            $table->decimal('discount', 20, 4)->default(0);
            $table->decimal('discount_percent', 20, 4)->default(0);
            $table->decimal('vat', 20, 4)->default(0);
            $table->decimal('total', 20, 4)->default(0);
            $table->decimal('last_price', 20, 4)->default(0);
            $table->dateTime('last_price_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['esb_purchase_order_id', 'esb_detail_id'], 'esb_po_item_detail_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esb_purchase_order_items');
        Schema::dropIfExists('esb_purchase_orders');
    }
};
