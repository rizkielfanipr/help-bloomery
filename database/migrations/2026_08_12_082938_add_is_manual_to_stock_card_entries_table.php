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
        Schema::table('stock_card_entries', function (Blueprint $table) {
            $table->boolean('is_manual')->default(false)->after('system_unit')
                ->comment('True if the staff added this product manually (not present in the ESB usage report for that date, e.g. before store closing).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_card_entries', function (Blueprint $table) {
            $table->dropColumn('is_manual');
        });
    }
};
