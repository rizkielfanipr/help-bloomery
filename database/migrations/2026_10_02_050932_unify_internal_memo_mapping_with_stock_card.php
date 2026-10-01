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
        DB::table('branches')
            ->whereNull('stock_card_esb_code_id')
            ->whereNotNull('internal_memo_esb_code_id')
            ->update(['stock_card_esb_code_id' => DB::raw('internal_memo_esb_code_id')]);

        Schema::table('branches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('internal_memo_esb_code_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('internal_memo_esb_code_id')
                ->nullable()
                ->after('stock_card_esb_code_id')
                ->constrained('branch_esb_codes')
                ->nullOnDelete();
        });

        DB::table('branches')
            ->whereNotNull('stock_card_esb_code_id')
            ->update(['internal_memo_esb_code_id' => DB::raw('stock_card_esb_code_id')]);
    }
};
