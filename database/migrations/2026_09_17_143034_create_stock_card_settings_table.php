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
        Schema::create('stock_card_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_code', 100)->unique();
            $table->boolean('all_categories')->default(true);
            $table->json('categories')->nullable();
            $table->boolean('show_uncategorized')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_card_settings');
    }
};
