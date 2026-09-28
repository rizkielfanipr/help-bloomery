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
        Schema::create('product_price_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('status')->default('running')->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('orders_synced')->default(0);
            $table->unsignedInteger('items_synced')->default(0);
            $table->unsignedInteger('products_snapshotted')->default(0);
            $table->unsignedInteger('failed_orders')->default(0);
            $table->json('errors')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_price_sync_runs');
    }
};
