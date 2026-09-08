<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rnd_product_regional_prices', function (Blueprint $table) {
            $table->boolean('has_separate_offline_prices')->default(false)->after('offline_price');
            $table->decimal('dine_in_price', 15, 2)->nullable()->after('has_separate_offline_prices');
            $table->decimal('takeaway_price', 15, 2)->nullable()->after('dine_in_price');
        });

        DB::table('rnd_product_regional_prices')->update([
            'dine_in_price' => DB::raw('offline_price'),
            'takeaway_price' => DB::raw('offline_price'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rnd_product_regional_prices', function (Blueprint $table) {
            $table->dropColumn(['has_separate_offline_prices', 'dine_in_price', 'takeaway_price']);
        });
    }
};
