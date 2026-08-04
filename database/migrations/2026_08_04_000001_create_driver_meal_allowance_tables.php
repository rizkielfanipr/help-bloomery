<?php

use App\Services\PermissionSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_meal_allowance_periods', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('report_year');
            $table->unsignedTinyInteger('report_month');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('open');
            $table->unsignedInteger('driver_count')->default(0);
            $table->unsignedInteger('trip_count')->default(0);
            $table->decimal('total_amount', 16, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();

            $table->unique(['report_year', 'report_month']);
            $table->unique(['start_date', 'end_date']);
        });

        Schema::create('driver_meal_allowance_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('period_id')->constrained('driver_meal_allowance_periods')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('trip_count')->default(0);
            $table->decimal('base_amount', 16, 2)->default(0);
            $table->decimal('adjustment_amount', 16, 2)->default(0);
            $table->text('adjustment_reason')->nullable();
            $table->decimal('final_amount', 16, 2)->default(0);
            $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('adjusted_at')->nullable();
            $table->timestamps();

            $table->unique(['period_id', 'driver_id']);
        });

        Schema::create('driver_meal_allowance_trip_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('period_id')->constrained('driver_meal_allowance_periods')->cascadeOnDelete();
            $table->foreignId('summary_id')->constrained('driver_meal_allowance_summaries')->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained('trips')->restrictOnDelete();
            $table->date('trip_date');
            $table->string('trip_code');
            $table->string('route_name')->nullable();
            $table->decimal('allowance_amount', 16, 2)->default(0);
            $table->string('amount_source')->default('trip_snapshot');
            $table->boolean('is_included')->default(true);
            $table->text('exclusion_reason')->nullable();
            $table->timestamps();

            $table->unique('trip_id');
            $table->index(['period_id', 'trip_date']);
        });

        app(PermissionSynchronizer::class)->sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_meal_allowance_trip_items');
        Schema::dropIfExists('driver_meal_allowance_summaries');
        Schema::dropIfExists('driver_meal_allowance_periods');
    }
};
