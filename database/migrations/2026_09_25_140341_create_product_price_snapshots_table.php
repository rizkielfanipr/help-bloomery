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
        Schema::create('product_price_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('snapshot_date')->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('product_detail_id')->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('product_code')->nullable()->index();
            $table->string('product_name')->index();
            $table->string('uom_name')->nullable();
            $table->decimal('total_quantity', 20, 4)->default(0);
            $table->decimal('total_amount', 20, 4)->default(0);
            $table->decimal('weighted_average_price', 20, 4)->default(0);
            $table->unsignedInteger('purchase_count')->default(0);
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['snapshot_date', 'product_detail_id'], 'product_price_snapshot_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_price_snapshots');
    }
};
