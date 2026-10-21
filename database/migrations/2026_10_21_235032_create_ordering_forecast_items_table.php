<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One recommendation per branch, company pair, product, and UOM inside a forecast run
 * (docs/ordering-recommendation-prd.md §11.4). The daily breakdown is a bounded JSON snapshot
 * (at most the 14-day horizon) because its only consumers are this item's chart, stockout date,
 * and detail; it never needs to be queried on its own (§11.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordering_forecast_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ordering_forecast_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('company_code', 50);
            $table->string('esb_branch_code', 50);
            $table->string('product_code', 100);
            $table->unsignedBigInteger('product_detail_id')->nullable();
            $table->string('product_name')->nullable();
            $table->string('category_name')->nullable();
            $table->string('uom', 50);
            $table->unsignedSmallInteger('valid_days')->default(0);
            $table->decimal('historical_demand', 18, 4)->nullable();
            $table->decimal('average_daily_demand', 18, 4)->nullable();
            $table->json('weekday_baseline')->nullable();
            $table->decimal('trend_raw', 10, 4)->nullable();
            $table->decimal('trend_final', 10, 4)->nullable();
            $table->string('trend_label', 20);
            $table->decimal('historical_waste', 18, 4)->nullable();
            $table->decimal('waste_rate_raw', 10, 4)->nullable();
            $table->decimal('waste_rate_capped', 10, 4)->nullable();
            $table->decimal('forecast_demand', 18, 4)->nullable();
            $table->decimal('expected_waste', 18, 4)->nullable();
            $table->decimal('buffer_percent', 6, 4);
            $table->decimal('safety_buffer', 18, 4)->nullable();
            $table->decimal('available_stock', 18, 4)->nullable();
            $table->timestamp('stock_observed_at')->nullable();
            $table->decimal('confirmed_inbound', 18, 4)->nullable();
            $table->decimal('gross_requirement', 18, 4)->nullable();
            $table->decimal('raw_recommendation', 18, 4)->nullable();
            $table->decimal('final_recommendation', 18, 4)->nullable();
            $table->decimal('coverage_days', 10, 2)->nullable();
            $table->date('predicted_stockout_date')->nullable();
            $table->string('confidence', 20);
            $table->json('reason_codes');
            $table->text('explanation');
            $table->json('daily_breakdown')->nullable();
            $table->string('selected_method', 40);
            $table->json('backtest')->nullable();
            $table->decimal('priority_score', 8, 4)->nullable();
            $table->timestamps();

            $table->unique(['ordering_forecast_run_id', 'company_code', 'esb_branch_code', 'product_code', 'uom'], 'ordering_forecast_items_identity_unique');
            $table->index(['ordering_forecast_run_id', 'priority_score'], 'ordering_forecast_items_run_priority_idx');
            $table->index(['ordering_forecast_run_id', 'confidence'], 'ordering_forecast_items_run_confidence_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordering_forecast_items');
    }
};
