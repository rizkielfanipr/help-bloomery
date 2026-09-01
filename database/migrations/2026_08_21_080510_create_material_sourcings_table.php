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
        Schema::create('material_sourcings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rnd_product_esb_material_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_name');
            $table->decimal('price', 20, 4);
            $table->string('moq')->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_sourcings');
    }
};
