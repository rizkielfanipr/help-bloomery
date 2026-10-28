<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/products/kitchen/production-schedule/prd.md §29.1: read-only snapshot of ESB Production Order headers.
 * The identity is company + order number. History outlives its mapping (null on delete) and a
 * Branch with snapshots cannot be deleted. No ESB call or backfill runs here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esb_production_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_esb_code_id')->nullable()->constrained('branch_esb_codes')->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('company_code', 50);
            $table->unsignedBigInteger('esb_branch_id');
            $table->string('esb_branch_name')->nullable();
            $table->string('production_order_num', 100);
            $table->date('production_order_date');
            $table->string('bom_type_id', 100)->nullable();
            $table->string('bom_type_name')->nullable();
            $table->string('status_id', 100)->nullable();
            $table->string('status_name')->nullable();
            $table->text('additional_info')->nullable();
            $table->string('created_by_name')->nullable();
            $table->dateTime('esb_created_at')->nullable();
            $table->string('edited_by_name')->nullable();
            $table->dateTime('esb_edited_at')->nullable();
            $table->string('authorized_by_name')->nullable();
            $table->dateTime('esb_authorized_at')->nullable();
            $table->timestamp('source_fetched_at')->nullable();
            $table->timestamp('source_last_seen_at')->nullable();
            $table->timestamp('details_synced_at')->nullable();
            $table->char('payload_hash', 64);
            $table->timestamps();

            $table->unique(['company_code', 'production_order_num'], 'esb_po_company_number_unique');
            $table->index(['branch_id', 'production_order_date'], 'esb_po_branch_date_index');
            $table->index(['branch_id', 'status_id', 'production_order_date'], 'esb_po_branch_status_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esb_production_orders');
    }
};
