<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily fact per branch, company pair, product, and UOM (docs/products/analytics/ordering-recommendation/prd.md
 * §11.2). The natural identity is the unique key so every rebuild is an idempotent upsert; the
 * product name is a snapshot only, never part of the identity. Quantities stay null when their
 * source has not been read successfully.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordering_daily_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('company_code', 50);
            $table->string('esb_branch_code', 50);
            $table->date('metric_date');
            $table->string('product_code', 100);
            $table->unsignedBigInteger('product_detail_id')->nullable();
            $table->string('product_name')->nullable();
            $table->string('category_name')->nullable();
            $table->string('uom', 50);
            $table->decimal('sales_driven_usage', 18, 4)->nullable();
            $table->decimal('pos_sales_usage', 18, 4)->nullable();
            $table->decimal('waste_quantity', 18, 4)->nullable();
            $table->decimal('goods_in_quantity', 18, 4)->nullable();
            $table->decimal('ending_stock', 18, 4)->nullable();
            $table->timestamp('stock_observed_at')->nullable();
            $table->foreignId('usage_sync_run_id')->nullable()->constrained('ordering_sync_runs')->nullOnDelete();
            $table->foreignId('stock_sync_run_id')->nullable()->constrained('ordering_sync_runs')->nullOnDelete();
            $table->string('input_version', 20);
            $table->timestamps();

            $table->unique(['branch_id', 'company_code', 'esb_branch_code', 'metric_date', 'product_code', 'uom'], 'ordering_daily_metrics_identity_unique');
            $table->index(['branch_id', 'metric_date'], 'ordering_daily_metrics_branch_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordering_daily_metrics');
    }
};
