<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/products/kitchen/production-schedule/prd.md §29.3: one row per sync execution of a mapping and window.
 * `active_key` holds the window key only while the run is queued or running, so the unique index
 * allows at most one active run per window while finished runs keep their history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esb_production_order_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('idempotency_key', 191);
            $table->string('active_key', 191)->nullable()->unique();
            $table->foreignId('branch_esb_code_id')->nullable()->constrained('branch_esb_codes')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('company_code', 50);
            $table->unsignedBigInteger('esb_branch_id')->nullable();
            $table->string('mode', 20);
            $table->date('date_from');
            $table->date('date_to');
            $table->string('status', 20);
            $table->unsignedInteger('last_page')->default(0);
            $table->unsignedInteger('pages_fetched')->default(0);
            $table->unsignedInteger('headers_received')->default(0);
            $table->unsignedInteger('headers_created')->default(0);
            $table->unsignedInteger('headers_updated')->default(0);
            $table->unsignedInteger('headers_unchanged')->default(0);
            $table->unsignedInteger('conflicts')->default(0);
            $table->unsignedInteger('details_expected')->default(0);
            $table->unsignedInteger('details_succeeded')->default(0);
            $table->unsignedInteger('details_failed')->default(0);
            $table->unsignedInteger('attempts')->default(0);
            $table->string('error_class', 30)->nullable();
            $table->string('error_code', 30)->nullable();
            $table->string('error_summary', 500)->nullable();
            $table->char('correlation_id', 36)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('list_completed_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('idempotency_key', 'esb_po_runs_key_index');
            $table->index(['branch_esb_code_id', 'list_completed_at'], 'esb_po_runs_mapping_completed_index');
            $table->index(['branch_id', 'created_at'], 'esb_po_runs_branch_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esb_production_order_sync_runs');
    }
};
