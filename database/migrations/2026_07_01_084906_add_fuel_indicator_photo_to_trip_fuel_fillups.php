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
        Schema::table('trip_fuel_fillups', function (Blueprint $table) {
            $table->string('fuel_indicator_photo')->nullable()->after('attachment_path');
            $table->decimal('price_per_liter', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('trip_fuel_fillups', function (Blueprint $table) {
            $table->dropColumn('fuel_indicator_photo');
        });
    }
};
