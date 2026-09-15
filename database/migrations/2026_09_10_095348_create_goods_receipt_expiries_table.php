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
        Schema::create('goods_receipt_expiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_item_id')->constrained()->cascadeOnDelete();
            $table->date('expired_date');
            $table->decimal('quantity', 20, 4);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_expiries');
    }
};
