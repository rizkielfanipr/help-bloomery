<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Request Sourcing from the Employee App (docs/products/purchasing/employee-sourcing-request/prd.md §14/§20).
 * Additive only: existing sourcing tables are not changed, and requests/history are never cascade-deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_sourcing_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 30)->nullable()->unique();
            $table->uuid('submission_key')->unique();
            $table->string('status', 30);
            $table->string('company_code', 10)->default('BLSS');
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requester_name');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('branch_name');
            $table->unsignedBigInteger('esb_product_id')->nullable();
            $table->unsignedBigInteger('esb_product_detail_id');
            $table->string('product_code', 50);
            $table->string('product_name');
            $table->string('category_name')->nullable();
            $table->string('sub_category_name')->nullable();
            $table->string('uom_name', 50);
            $table->text('reason');
            $table->decimal('estimated_quantity', 15, 4)->nullable();
            $table->date('target_date')->nullable();
            $table->text('notes')->nullable();
            $table->json('attachments')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->text('last_decision_reason')->nullable();
            $table->foreignId('last_decision_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_decision_at')->nullable();
            $table->foreignId('rnd_product_esb_material_id')->nullable()->unique()->constrained('rnd_product_esb_materials')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'submitted_at'], 'msr_status_submitted_index');
            $table->index(['branch_id', 'status'], 'msr_branch_status_index');
            $table->index(['requester_id', 'submitted_at'], 'msr_requester_submitted_index');
            $table->index(['assigned_to', 'status'], 'msr_assigned_status_index');
            $table->index('product_code', 'msr_product_code_index');
            $table->index('esb_product_detail_id', 'msr_product_detail_index');
            $table->index('target_date', 'msr_target_date_index');
        });

        Schema::create('material_sourcing_request_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_sourcing_request_id')->constrained('material_sourcing_requests', indexName: 'msr_histories_request_fk')->restrictOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_sourcing_request_status_histories');
        Schema::dropIfExists('material_sourcing_requests');
    }
};
