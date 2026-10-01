<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/rnd-internal-memo-multi-branch-prd.md §8, Phase 0 decision #1-2: there is no generic
     * "primary ESB mapping" concept on `branch_esb_codes` (no is_primary/priority column), and
     * Stock Card's `stock_card_esb_code_id` is explicitly scoped to that feature only (per its own
     * UI copy: "Hanya mapping ini yang digunakan oleh Stock Card"). R&D Internal Memo needs its own
     * field, mirroring that exact pattern rather than reusing or inventing a shared flag.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->foreignId('internal_memo_esb_code_id')
                ->nullable()
                ->after('stock_card_esb_code_id')
                ->constrained('branch_esb_codes')
                ->nullOnDelete();
        });

        // Same one-time, unambiguous-only backfill as the Stock Card migration: a branch with
        // exactly one active mapping can be auto-assigned; 2+ active mappings are left null,
        // requiring an explicit admin decision (docs §8 resolution order step 3-4).
        DB::table('branch_esb_codes')
            ->where('is_active', true)
            ->select('branch_id', DB::raw('MIN(id) as mapping_id'), DB::raw('COUNT(*) as mapping_count'))
            ->groupBy('branch_id')
            ->having('mapping_count', 1)
            ->orderBy('branch_id')
            ->get()
            ->each(fn (object $mapping) => DB::table('branches')
                ->where('id', $mapping->branch_id)
                ->update(['internal_memo_esb_code_id' => $mapping->mapping_id]));
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('internal_memo_esb_code_id');
        });
    }
};
