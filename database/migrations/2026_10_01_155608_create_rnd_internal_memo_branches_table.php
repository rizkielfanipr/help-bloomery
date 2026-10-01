<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** docs/rnd-internal-memo-multi-branch-prd.md §9.1. */
    public function up(): void
    {
        if (Schema::hasTable('rnd_internal_memo_branches')) {
            Schema::table('rnd_internal_memo_branches', function (Blueprint $table): void {
                if (! Schema::hasIndex('rnd_internal_memo_branches', 'rnd_memo_branch_mapping_unique')) {
                    $table->unique(['rnd_internal_memo_id', 'branch_esb_code_id'], 'rnd_memo_branch_mapping_unique');
                }

                if (! Schema::hasIndex('rnd_internal_memo_branches', 'rnd_memo_branch_sync_idx')) {
                    $table->index(['rnd_internal_memo_id', 'catalog_sync_status'], 'rnd_memo_branch_sync_idx');
                }
            });

            return;
        }

        Schema::create('rnd_internal_memo_branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_internal_memo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            // Not nullable: a row here only ever represents an already-resolved mapping snapshot
            // (§8 — a branch with no resolvable mapping cannot be selected in the first place, so
            // no row is ever created for it). restrictOnDelete so an admin can't delete a
            // branch_esb_codes row a live Memo still snapshots from without an explicit decision.
            $table->foreignId('branch_esb_code_id')->constrained('branch_esb_codes')->restrictOnDelete();
            $table->string('branch_name_snapshot');
            $table->string('company_code_snapshot', 10);
            $table->string('branch_code_snapshot', 50);
            $table->unsignedBigInteger('esb_branch_id_snapshot')->nullable();
            $table->string('catalog_sync_status', 20)->default('pending');
            $table->timestamp('catalog_synced_at')->nullable();
            $table->text('catalog_sync_error')->nullable();
            $table->timestamps();

            $table->unique(['rnd_internal_memo_id', 'branch_esb_code_id'], 'rnd_memo_branch_mapping_unique');
            $table->index(['rnd_internal_memo_id', 'catalog_sync_status'], 'rnd_memo_branch_sync_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_internal_memo_branches');
    }
};
