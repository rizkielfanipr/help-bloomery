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
        Schema::create('rnd_internal_memo_menu_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('company_code', 20);
            $table->string('branch_code', 50);
            $table->unsignedBigInteger('menu_id');
            $table->string('menu_code')->nullable();
            $table->string('menu_name');
            $table->unsignedBigInteger('bom_id')->default(0);
            $table->string('bom_name')->nullable();
            $table->string('category_detail')->nullable();
            $table->boolean('flag_active')->default(true);
            $table->json('raw_snapshot')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['company_code', 'branch_code', 'menu_id'], 'rnd_memo_catalog_context_menu_unique');
            $table->index(['company_code', 'branch_code', 'flag_active'], 'rnd_memo_catalog_context_active_idx');
            $table->index(['menu_name', 'menu_code'], 'rnd_memo_catalog_search_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rnd_internal_memo_menu_catalogs');
    }
};
