<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/rnd-internal-memo-brand-prd.md §13.1. Additive only: `brand_id` stays nullable at the
 * database level so legacy Memos without a deterministic Brand keep loading; new create/edit
 * flows require it in the application. `nullOnDelete` plus the name snapshot keep history readable
 * after a Master Brand is removed. Backfill is a separate command (rnd:backfill-internal-memo-brands).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rnd_internal_memos', function (Blueprint $table): void {
            // Explicit index: MySQL would otherwise silently reuse a later (brand_id, ...) unique
            // index for this foreign key and refuse to drop that index on rollback.
            $table->foreignId('brand_id')->nullable()->after('company_code')->index()->constrained('brands')->nullOnDelete();
            $table->string('brand_name_snapshot')->nullable()->after('brand_id');
        });
    }

    public function down(): void
    {
        Schema::table('rnd_internal_memos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('brand_id');
            $table->dropColumn('brand_name_snapshot');
        });
    }
};
