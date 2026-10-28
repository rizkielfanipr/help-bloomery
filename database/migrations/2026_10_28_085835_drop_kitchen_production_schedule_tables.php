<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/products/kitchen/production-schedule/prd.md §38 (K17): the local production plan is removed, so its
 * tables are dropped — children first because of the restrictive foreign keys. The transitions
 * table only exists where an older, removed version was deployed.
 *
 * Destructive: back the tables up before running this on a shared environment. `down()` only
 * recreates the empty structure from the original create migrations so an older release can
 * boot again; the data has to be restored from that backup (§38.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('kitchen_production_schedule_items');
        Schema::dropIfExists('kitchen_production_schedule_transitions');
        Schema::dropIfExists('kitchen_production_schedules');
    }

    public function down(): void
    {
        if (! Schema::hasTable('kitchen_production_schedules')) {
            Schema::create('kitchen_production_schedules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('branch_id')->constrained()->restrictOnDelete();
                $table->date('production_date');
                $table->string('status', 20)->default('scheduled');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->timestamps();

                $table->unique(['branch_id', 'production_date'], 'kitchen_schedules_branch_date_unique');
                $table->index(['status', 'production_date'], 'kitchen_schedules_status_date_index');
                $table->index(['branch_id', 'status', 'production_date'], 'kitchen_schedules_branch_status_date_index');
            });
        }

        if (! Schema::hasTable('kitchen_production_schedule_items')) {
            Schema::create('kitchen_production_schedule_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('kitchen_production_schedule_id')
                    ->constrained('kitchen_production_schedules', 'id', 'kitchen_schedule_items_schedule_fk')
                    ->restrictOnDelete();
                $table->foreignId('rnd_bom_catalog_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedBigInteger('esb_bom_id');
                $table->string('bom_code')->nullable();
                $table->string('bom_name')->nullable();
                $table->unsignedInteger('bom_type_id')->nullable();
                $table->unsignedBigInteger('product_detail_id')->nullable();
                $table->string('product_code')->nullable();
                $table->string('product_name')->nullable();
                $table->string('uom_name', 100)->nullable();
                $table->decimal('planned_quantity', 18, 4);
                $table->unsignedInteger('sequence');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['kitchen_production_schedule_id', 'esb_bom_id'], 'kitchen_schedule_items_bom_unique');
                $table->unique(['kitchen_production_schedule_id', 'sequence'], 'kitchen_schedule_items_sequence_unique');
                $table->index('esb_bom_id');
            });
        }
    }
};
