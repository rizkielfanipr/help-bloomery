<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->decimal('physical_qty', 20, 4)->default(0);
            $table->decimal('accepted_qty', 20, 4)->default(0);
            $table->decimal('hold_qty', 20, 4)->default(0);
            $table->decimal('rejected_qty', 20, 4)->default(0);
            $table->string('measurement_method')->default('count');
            $table->decimal('variance_percentage', 9, 4)->default(0);
            $table->decimal('tolerance_percentage', 9, 4)->default(0);
            $table->string('quantity_check_result')->default('pass');
            $table->string('color_check_result')->default('pass');
            $table->string('texture_check_result')->default('pass');
            $table->string('packaging_check_result')->default('pass');
            $table->string('contamination_check_result')->default('pass');
            $table->string('temperature_category')->default('ambient');
            $table->decimal('actual_temperature', 8, 2)->nullable();
            $table->decimal('min_temperature', 8, 2)->nullable();
            $table->decimal('max_temperature', 8, 2)->nullable();
            $table->string('cold_chain_result')->default('not_applicable');
            $table->boolean('shelf_life_required')->default(false);
            $table->decimal('minimum_shelf_life_percentage', 5, 2)->default(80);
            $table->string('shelf_life_result')->default('not_applicable');
            $table->boolean('sampling_required')->default(false);
            $table->string('sampling_method')->nullable();
            $table->string('sampling_result')->default('not_applicable');
            $table->text('sampling_notes')->nullable();
            $table->string('disposition')->default('accepted')->index();
            $table->string('quarantine_location')->nullable();
            $table->string('rejection_category')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('evidence_photos')->nullable();
            $table->foreignId('qc_inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('qc_inspected_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qc_inspected_by');
            $table->dropColumn([
                'physical_qty', 'accepted_qty', 'hold_qty', 'rejected_qty', 'measurement_method',
                'variance_percentage', 'tolerance_percentage', 'quantity_check_result',
                'color_check_result', 'texture_check_result', 'packaging_check_result',
                'contamination_check_result', 'temperature_category', 'actual_temperature',
                'min_temperature', 'max_temperature', 'cold_chain_result', 'shelf_life_required',
                'minimum_shelf_life_percentage', 'shelf_life_result', 'sampling_required',
                'sampling_method', 'sampling_result', 'sampling_notes', 'disposition',
                'quarantine_location', 'rejection_category', 'rejection_reason',
                'evidence_photos', 'qc_inspected_at',
            ]);
        });
    }
};
