<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_internal_memo_menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_internal_memo_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('esb_menu_id');
            $table->string('menu_code')->nullable();
            $table->string('menu_name');
            $table->string('category_detail')->nullable();
            $table->unsignedBigInteger('esb_bom_id');
            $table->string('bom_name')->nullable();
            $table->date('release_date');
            $table->decimal('forecast_quantity', 14, 2)->default(0);
            $table->decimal('shelf_life_value', 10, 2)->nullable();
            $table->string('shelf_life_unit', 20)->nullable();
            $table->string('storage_condition')->nullable();
            $table->text('shelf_life_notes')->nullable();
            $table->string('sync_status', 20)->default('pending');
            $table->timestamp('synced_at')->nullable();
            $table->text('sync_error')->nullable();
            $table->json('menu_snapshot');
            $table->json('bom_snapshot')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->unique(['rnd_internal_memo_id', 'esb_menu_id']);
            $table->index(['rnd_internal_memo_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_internal_memo_menus');
    }
};
