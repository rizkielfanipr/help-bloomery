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
            $table->decimal('gofood_price', 15, 2)->nullable()->after('online_price');
            $table->decimal('grabfood_price', 15, 2)->nullable()->after('gofood_price');
            $table->decimal('shopeefood_price', 15, 2)->nullable()->after('grabfood_price');
        });

        DB::table('rnd_product_regional_prices')->update([
            'gofood_price' => DB::raw('online_price'),
            'grabfood_price' => DB::raw('online_price'),
            'shopeefood_price' => DB::raw('online_price'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rnd_product_regional_prices', function (Blueprint $table) {
            $table->dropColumn(['gofood_price', 'grabfood_price', 'shopeefood_price']);
        });
    }
};
