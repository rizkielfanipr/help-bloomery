<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/kitchen-production-schedule-prd.md §11.2: one BOM Assembly per schedule with its planned
 * output quantity and a lightweight identity snapshot. Schedules are never deleted on the MVP, so
 * items restrict deletion of their header; a removed catalog row only clears the reference.
 */
return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('kitchen_production_schedule_items');
    }
};
