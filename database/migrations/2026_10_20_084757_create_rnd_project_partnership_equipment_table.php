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
        Schema::create('rnd_project_partnership_equipment', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_project_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_detail_id');
            $table->string('product_code')->nullable();
            $table->string('product_name');
            $table->string('uom_name', 100)->nullable();
            $table->decimal('quantity', 14, 4)->default(1);
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['rnd_project_id', 'product_detail_id'], 'rnd_project_partner_equipment_unique');
            $table->index(['rnd_project_id', 'sort_order'], 'rnd_project_partner_equipment_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rnd_project_partnership_equipment');
    }
};
