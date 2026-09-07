<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_maintenances', function (Blueprint $table): void {
            $table->id();
            $table->string('maintenance_number')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('maintenance_year');
            $table->unsignedTinyInteger('maintenance_month');
            $table->string('status')->default('draft');
            $table->decimal('score', 6, 2)->default(0);
            $table->unsignedInteger('earned_points')->default(0);
            $table->unsignedInteger('maximum_points')->default(0);
            $table->text('overall_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'maintenance_year', 'maintenance_month'], 'technician_maintenance_period_unique');
            $table->index(['status', 'maintenance_year', 'maintenance_month'], 'technician_maintenance_status_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_maintenances');
    }
};
