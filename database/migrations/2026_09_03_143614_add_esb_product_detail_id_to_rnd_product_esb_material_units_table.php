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
        Schema::table('rnd_product_esb_material_units', function (Blueprint $table) {
            $table->unsignedBigInteger('esb_product_detail_id')->nullable()->after('uom_name')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rnd_product_esb_material_units', function (Blueprint $table) {
            $table->dropColumn('esb_product_detail_id');
        });
    }
};
