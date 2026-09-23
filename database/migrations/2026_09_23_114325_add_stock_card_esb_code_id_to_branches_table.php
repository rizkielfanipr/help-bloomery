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
        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('stock_card_esb_code_id')
                ->nullable()
                ->after('is_active')
                ->constrained('branch_esb_codes')
                ->nullOnDelete();
        });

        DB::table('branch_esb_codes')
            ->where('is_active', true)
            ->select('branch_id', DB::raw('MIN(id) as mapping_id'), DB::raw('COUNT(*) as mapping_count'))
            ->groupBy('branch_id')
            ->having('mapping_count', 1)
            ->orderBy('branch_id')
            ->get()
            ->each(fn (object $mapping) => DB::table('branches')
                ->where('id', $mapping->branch_id)
                ->update(['stock_card_esb_code_id' => $mapping->mapping_id]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_card_esb_code_id');
        });
    }
};
