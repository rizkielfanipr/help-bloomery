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
        Schema::create('rnd_bom_catalogs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('esb_bom_id')->unique();
            $table->string('bom_code')->nullable();
            $table->string('bom_name')->nullable();
            $table->unsignedInteger('bom_type_id')->nullable();
            $table->string('bom_type_name')->nullable();
            $table->unsignedBigInteger('product_detail_id')->nullable();
            $table->string('product_code')->nullable();
            $table->string('product_name')->nullable();
            $table->string('uom_name', 100)->nullable();
            $table->unsignedInteger('component_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('detail_snapshot')->nullable();
            $table->string('esb_edited_at')->nullable();
            $table->string('sync_status', 30)->default('synced');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['bom_type_id', 'is_active']);
            $table->index('product_detail_id');
            $table->index('sync_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rnd_bom_catalogs');
    }
};
