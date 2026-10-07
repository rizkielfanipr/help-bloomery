<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/rnd-internal-memo-brand-prd.md §13.5. One sync state per Company Code (always BLSS for Memo
 * Internal), replacing the per Memo–Branch status columns. Never keyed by Brand.
 * `technical_branch_code` records which ESB branch the last successful sync used, because the
 * Master Menu endpoint needs one; the picker reads the local snapshot of that branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_internal_memo_catalog_syncs', function (Blueprint $table) {
            $table->id();
            $table->string('company_code', 20);
            $table->string('technical_branch_code', 50)->nullable();
            $table->string('status', 20)->default('idle');
            $table->unsignedInteger('menu_count')->nullable();
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->string('last_error', 1000)->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('company_code', 'rnd_memo_catalog_sync_company_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_internal_memo_catalog_syncs');
    }
};
