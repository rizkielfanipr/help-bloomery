<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §17, §19.4: Finance Pricelist queue. `eligibility_key` makes one eligibility event create at
 * most one request; `active_key` (material + Product Detail) is only filled while the request is
 * active, so a material/detail never has two open requests. The supplier offer is an optional
 * reference snapshot and never the official price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_material_pricing_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('rnd_product_esb_material_id')->constrained(indexName: 'rnd_material_pricing_material_fk')->restrictOnDelete();
            $table->unsignedBigInteger('esb_product_id')->nullable();
            $table->unsignedBigInteger('product_detail_id');
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->string('uom_name', 50)->nullable();
            $table->string('origin', 20);
            $table->foreignId('rnd_material_sample_cycle_id')->nullable()->constrained(indexName: 'rnd_material_pricing_cycle_fk')->nullOnDelete();
            $table->foreignId('material_sourcing_id')->nullable()->constrained('material_sourcings')->nullOnDelete();
            $table->string('supplier_name')->nullable();
            $table->decimal('offer_price', 20, 4)->nullable();
            $table->unsignedSmallInteger('revision')->default(1);
            $table->string('eligibility_key', 150)->unique();
            $table->string('active_key', 120)->nullable()->unique();
            $table->string('status', 30);
            $table->string('company_code', 20)->nullable();
            $table->string('branch_scope', 255)->nullable();
            $table->decimal('price', 20, 4)->nullable();
            $table->date('effective_date')->nullable();
            $table->text('finance_notes')->nullable();
            $table->string('verification_method', 20)->nullable();
            $table->string('external_reference', 120)->nullable();
            $table->string('idempotency_key', 120)->unique();
            $table->json('request_summary')->nullable();
            $table->json('response_summary')->nullable();
            $table->text('failure_reason')->nullable();
            $table->text('conflict_reason')->nullable();
            $table->foreignId('drafted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('drafted_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('queued_at');
            $table->timestamps();

            $table->index(['status', 'queued_at'], 'rnd_material_pricing_status_queued_index');
            $table->index('product_detail_id', 'rnd_material_pricing_product_detail_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_material_pricing_requests');
    }
};
