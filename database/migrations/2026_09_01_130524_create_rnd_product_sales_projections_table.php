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
        Schema::create('rnd_product_sales_projections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rnd_project_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_region_id')->constrained()->restrictOnDelete();
            $table->date('projection_month');
            $table->string('channel', 20)->default('all');
            $table->decimal('target_quantity', 15, 2);
            $table->decimal('target_revenue', 18, 2);
            $table->unsignedInteger('target_outlets')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rnd_project_product_id', 'projection_month'], 'rnd_projection_product_month_idx');
            $table->unique(
                ['rnd_project_product_id', 'sales_region_id', 'projection_month', 'channel'],
                'rnd_product_projection_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rnd_product_sales_projections');
    }
};
