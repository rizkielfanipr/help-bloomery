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
        Schema::table('stock_card_entries', function (Blueprint $table) {
            $table->decimal('system_qty', 20, 4)->nullable()->default(null)->change();
        });

        DB::table('stock_card_entries')->where('system_qty', 0)->update(['system_qty' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('stock_card_entries')->whereNull('system_qty')->update(['system_qty' => 0]);

        Schema::table('stock_card_entries', function (Blueprint $table) {
            $table->decimal('system_qty', 20, 4)->nullable(false)->default(0)->change();
        });
    }
};
