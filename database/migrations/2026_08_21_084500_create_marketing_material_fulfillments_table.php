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
        Schema::create('marketing_material_fulfillments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rnd_project_marketing_material_id')->unique()
                ->constrained('rnd_project_marketing_materials', indexName: 'marketing_fulfillments_material_id_foreign')
                ->cascadeOnDelete();
            $table->string('status')->default('not_started');

            $table->string('vendor_name')->nullable();
            $table->date('order_date')->nullable();
            $table->date('estimated_completion_date')->nullable();
            $table->text('purchasing_notes')->nullable();
            $table->foreignId('ordered_by')->nullable()
                ->constrained('users', indexName: 'marketing_fulfillments_ordered_by_foreign')
                ->nullOnDelete();
            $table->timestamp('ordered_at')->nullable();

            $table->unsignedInteger('received_quantity')->nullable();
            $table->date('received_date')->nullable();
            $table->foreignId('location_id')->nullable()
                ->constrained('locations', indexName: 'marketing_fulfillments_location_id_foreign')
                ->nullOnDelete();
            $table->text('inventory_notes')->nullable();
            $table->foreignId('received_by')->nullable()
                ->constrained('users', indexName: 'marketing_fulfillments_received_by_foreign')
                ->nullOnDelete();
            $table->timestamp('received_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_material_fulfillments');
    }
};
