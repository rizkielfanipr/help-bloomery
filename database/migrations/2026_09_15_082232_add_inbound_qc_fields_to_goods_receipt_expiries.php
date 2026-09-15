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
        Schema::table('goods_receipt_expiries', function (Blueprint $table) {
            $table->string('batch_number')->nullable()->after('goods_receipt_item_id');
            $table->date('manufactured_date')->nullable()->after('batch_number');
            $table->decimal('accepted_quantity', 20, 4)->default(0);
            $table->decimal('hold_quantity', 20, 4)->default(0);
            $table->decimal('rejected_quantity', 20, 4)->default(0);
            $table->decimal('shelf_life_remaining_percentage', 7, 2)->nullable();
            $table->string('qc_result')->default('pass');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_receipt_expiries', function (Blueprint $table) {
            $table->dropColumn([
                'batch_number', 'manufactured_date', 'accepted_quantity', 'hold_quantity',
                'rejected_quantity', 'shelf_life_remaining_percentage', 'qc_result',
            ]);
        });
    }
};
