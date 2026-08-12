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
        Schema::table('stock_card_approvals', function (Blueprint $table) {
            $table->string('stage', 20)->default('supervisor')->after('stock_card_id');
        });

        DB::table('stock_card_approvals')->where('action', 'submitted')->update(['stage' => 'submitter']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_card_approvals', function (Blueprint $table) {
            $table->dropColumn('stage');
        });
    }
};
