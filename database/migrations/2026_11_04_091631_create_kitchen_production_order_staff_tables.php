<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff Produksi per Production Order detail (docs/products/kitchen/production-staff-assignment/prd.md §14).
 * Additive only: the ESB snapshot tables are not changed and nothing is backfilled. Assignments are
 * current rows (released rows are deleted, history lives in the append-only events table), so the
 * detail + employee unique key holds for every active row. Employee, Branch, and actor references
 * are nullable and nulled on delete; the identity snapshot keeps every row readable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_production_order_staff_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('esb_production_order_detail_id')
                ->constrained('esb_production_order_details', indexName: 'kpo_staff_assignments_detail_fk')
                ->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_code');
            $table->string('employee_name');
            $table->string('employee_position')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('branch_name');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamps();

            $table->unique(['esb_production_order_detail_id', 'employee_id'], 'kpo_staff_assignments_detail_employee_unique');
        });

        Schema::create('kitchen_production_order_staff_assignment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('esb_production_order_id')->nullable()
                ->constrained('esb_production_orders', indexName: 'kpo_staff_events_order_fk')
                ->nullOnDelete();
            $table->foreignId('esb_production_order_detail_id')->nullable()
                ->constrained('esb_production_order_details', indexName: 'kpo_staff_events_detail_fk')
                ->nullOnDelete();
            $table->string('event', 20);
            $table->foreignId('employee_id')->nullable()
                ->constrained(indexName: 'kpo_staff_events_employee_fk')
                ->nullOnDelete();
            $table->string('employee_code');
            $table->string('employee_name');
            $table->string('employee_position')->nullable();
            $table->foreignId('branch_id')->nullable()
                ->constrained(indexName: 'kpo_staff_events_branch_fk')
                ->nullOnDelete();
            $table->string('branch_name');
            $table->string('production_order_num');
            $table->string('bom_name')->nullable();
            $table->string('product_name')->nullable();
            $table->foreignId('actor_id')->nullable()
                ->constrained('users', indexName: 'kpo_staff_events_actor_fk')
                ->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['esb_production_order_detail_id', 'created_at'], 'kpo_staff_events_detail_created_index');
        });
    }

    /**
     * Structure rollback for local/staging rehearsal only. Production rollback keeps both tables
     * (prd.md §23): revoke the permission and roll back the application instead.
     */
    public function down(): void
    {
        Schema::dropIfExists('kitchen_production_order_staff_assignment_events');
        Schema::dropIfExists('kitchen_production_order_staff_assignments');
    }
};
