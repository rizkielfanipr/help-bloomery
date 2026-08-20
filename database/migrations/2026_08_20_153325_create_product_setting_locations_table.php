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
        Schema::create('product_setting_locations', function (Blueprint $table) {
            $table->id();
            $table->string('product_code');
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->foreign('product_code')->references('product_code')->on('product_settings')->cascadeOnDelete();
            $table->unique(['product_code', 'location_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_setting_locations');
    }
};
