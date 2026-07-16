<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_card_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_card_id')->constrained()->cascadeOnDelete();
            $table->string('product_code', 100)->default('');
            $table->string('product_name', 255);
            $table->decimal('system_qty', 20, 4)->default(0);
            $table->string('system_unit', 50)->default('');
            $table->decimal('actual_qty', 20, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['stock_card_id', 'product_code'], 'sce_stock_card_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_card_entries');
    }
};
