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
        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('purchase_detail_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_detail_id')->index();
            $table->string('product_code')->nullable();
            $table->string('product_name');
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->string('uom_name')->nullable();
            $table->decimal('ordered_qty', 20, 4)->default(0);
            $table->decimal('outstanding_qty', 20, 4)->default(0);
            $table->decimal('received_qty', 20, 4);
            $table->decimal('deviation_value', 20, 4)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('esb_detail_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
    }
};
