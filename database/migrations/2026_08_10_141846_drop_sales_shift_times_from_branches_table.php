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
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn([
                'sales_shift_1_start', 'sales_shift_1_end',
                'sales_shift_2_start', 'sales_shift_2_end',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->time('sales_shift_1_start')->default('09:00:00');
            $table->time('sales_shift_1_end')->default('15:00:00');
            $table->time('sales_shift_2_start')->default('15:00:00');
            $table->time('sales_shift_2_end')->default('21:00:00');
        });
    }
};
