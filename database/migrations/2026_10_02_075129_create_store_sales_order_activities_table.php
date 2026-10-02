<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** docs/store-sales-order-prd.md §15.3. Append-only through normal workflow. */
    public function up(): void
    {
        Schema::create('store_sales_order_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_sales_order_id')->constrained()->cascadeOnDelete();
            $table->string('activity_type', 30);
            $table->string('previous_status', 20)->nullable();
            $table->string('new_status', 20)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('store_sales_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_sales_order_activities');
    }
};
