<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §16.1: one sample hand-over → receipt → evaluation cycle. A resample opens the next cycle
 * number; earlier cycles are kept as history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_material_sample_cycles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_product_esb_material_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('cycle_number');
            $table->foreignId('material_sourcing_id')->nullable()->constrained('material_sourcings')->nullOnDelete();
            $table->string('status', 30);
            $table->decimal('quantity', 20, 4)->nullable();
            $table->string('uom_name', 50)->nullable();
            $table->text('handover_notes')->nullable();
            $table->foreignId('handed_over_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handed_over_at')->nullable();
            $table->decimal('received_quantity', 20, 4)->nullable();
            $table->string('received_condition', 30)->nullable();
            $table->text('receipt_notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->string('evaluation', 30)->nullable();
            $table->text('evaluation_reason')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->unique(['rnd_product_esb_material_id', 'cycle_number'], 'rnd_material_sample_cycles_number_unique');
            $table->index(['status', 'updated_at'], 'rnd_material_sample_cycles_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_material_sample_cycles');
    }
};
