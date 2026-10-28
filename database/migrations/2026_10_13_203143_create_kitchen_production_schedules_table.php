<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/products/kitchen/production-schedule/prd.md §11.1: one production schedule header per Branch per
 * local production date. History is retained: a Branch cannot be deleted while it has schedules,
 * and a deleted user only clears the actor reference.
 */
return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('kitchen_production_schedules');
    }
};
