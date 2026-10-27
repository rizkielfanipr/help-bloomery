<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One ESB read attempt per source, branch, company pair, and date
 * (docs/products/analytics/ordering-recommendation/prd.md §11.1). The idempotency key makes a rerun reuse its row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordering_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('idempotency_key', 191)->unique();
            $table->string('source', 30);
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('company_code', 50);
            $table->string('esb_branch_code', 50);
            $table->date('period_from');
            $table->date('period_to');
            $table->string('status', 20);
            $table->unsignedInteger('rows_received')->default(0);
            $table->unsignedInteger('rows_stored')->default(0);
            $table->unsignedInteger('pages_fetched')->default(0);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('error_summary', 500)->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'source', 'period_from', 'status'], 'ordering_sync_runs_branch_source_period_idx');
            $table->index('correlation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordering_sync_runs');
    }
};
