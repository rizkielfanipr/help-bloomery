<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** docs/rnd-internal-memo-multi-branch-prd.md §9.2 — which branches a merged Menu is available from. */
    public function up(): void
    {
        Schema::create('rnd_internal_memo_menu_branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_internal_memo_menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rnd_internal_memo_branch_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['rnd_internal_memo_menu_id', 'rnd_internal_memo_branch_id'], 'rnd_memo_menu_branches_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_internal_memo_menu_branches');
    }
};
