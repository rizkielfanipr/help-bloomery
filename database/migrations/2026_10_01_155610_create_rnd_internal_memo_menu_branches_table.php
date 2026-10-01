<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** docs/rnd-internal-memo-multi-branch-prd.md §9.2 — which branches a merged Menu is available from. */
    public function up(): void
    {
        if (Schema::hasTable('rnd_internal_memo_menu_branches')) {
            $foreignNames = collect(Schema::getForeignKeys('rnd_internal_memo_menu_branches'))->pluck('name');
            $needsMenuForeign = ! $foreignNames->contains('rnd_memo_menu_branch_menu_fk');
            $needsBranchForeign = ! $foreignNames->contains('rnd_memo_menu_branch_branch_fk');
            $needsUnique = ! Schema::hasIndex('rnd_internal_memo_menu_branches', 'rnd_memo_menu_branches_unique');

            Schema::table('rnd_internal_memo_menu_branches', function (Blueprint $table) use ($needsMenuForeign, $needsBranchForeign, $needsUnique): void {
                if ($needsMenuForeign) {
                    $table->foreign('rnd_internal_memo_menu_id', 'rnd_memo_menu_branch_menu_fk')
                        ->references('id')->on('rnd_internal_memo_menus')->cascadeOnDelete();
                }

                if ($needsBranchForeign) {
                    $table->foreign('rnd_internal_memo_branch_id', 'rnd_memo_menu_branch_branch_fk')
                        ->references('id')->on('rnd_internal_memo_branches')->cascadeOnDelete();
                }

                if ($needsUnique) {
                    $table->unique(['rnd_internal_memo_menu_id', 'rnd_internal_memo_branch_id'], 'rnd_memo_menu_branches_unique');
                }
            });

            return;
        }

        Schema::create('rnd_internal_memo_menu_branches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('rnd_internal_memo_menu_id');
            $table->unsignedBigInteger('rnd_internal_memo_branch_id');
            $table->timestamps();

            $table->foreign('rnd_internal_memo_menu_id', 'rnd_memo_menu_branch_menu_fk')
                ->references('id')->on('rnd_internal_memo_menus')->cascadeOnDelete();
            $table->foreign('rnd_internal_memo_branch_id', 'rnd_memo_menu_branch_branch_fk')
                ->references('id')->on('rnd_internal_memo_branches')->cascadeOnDelete();
            $table->unique(['rnd_internal_memo_menu_id', 'rnd_internal_memo_branch_id'], 'rnd_memo_menu_branches_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_internal_memo_menu_branches');
    }
};
