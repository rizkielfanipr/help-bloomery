<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** docs/store-sales-order-prd.md §15.2. Local operational notes, not an official ESB line item (§9.3). */
    public function up(): void
    {
        Schema::create('store_sales_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_sales_order_id')->constrained()->cascadeOnDelete();
            $table->string('product_type', 40);
            $table->string('custom_detail', 500)->nullable();
            $table->decimal('quantity', 10, 2);
            $table->string('notes', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(['store_sales_order_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_sales_order_items');
    }
};
