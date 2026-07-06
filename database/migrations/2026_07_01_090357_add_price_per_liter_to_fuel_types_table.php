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
        Schema::table('fuel_types', function (Blueprint $table) {
            $table->unsignedInteger('price_per_liter')->default(0)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_types', function (Blueprint $table) {
            $table->dropColumn('price_per_liter');
        });
    }
};
