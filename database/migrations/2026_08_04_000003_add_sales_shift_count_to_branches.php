<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->unsignedTinyInteger('sales_shift_count')->default(2)->after('sales_shift_1_end');
            $table->time('sales_shift_2_start')->nullable()->default('15:00:00')->change();
            $table->time('sales_shift_2_end')->nullable()->default('21:00:00')->change();
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn('sales_shift_count');
            $table->time('sales_shift_2_start')->default('15:00:00')->change();
            $table->time('sales_shift_2_end')->default('21:00:00')->change();
        });
    }
};
