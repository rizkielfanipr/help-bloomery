<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/operations/monitoring/prd.md §19: daily aggregates used by the Overview 7-day trend so the
 * dashboard never scans detail rows older than one day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_daily_aggregates', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('environment', 30);
            $table->string('metric', 30);
            $table->string('dimension', 80);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('problems')->default(0);
            $table->unsignedInteger('avg_response_ms')->nullable();
            $table->timestamps();

            $table->unique(['date', 'environment', 'metric', 'dimension'], 'monitoring_daily_aggregates_unique');
            $table->index(['environment', 'metric', 'date'], 'monitoring_daily_aggregates_env_metric_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_daily_aggregates');
    }
};
