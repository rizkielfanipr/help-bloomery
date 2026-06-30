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
        Schema::table('casual_positions', function (Blueprint $table) {
            $table->decimal('overtime_rate_per_hour', 12, 2)->nullable()->after('fee_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('casual_positions', function (Blueprint $table) {
            $table->dropColumn('overtime_rate_per_hour');
        });
    }
};
