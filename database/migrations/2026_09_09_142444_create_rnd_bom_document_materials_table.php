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
        Schema::create('rnd_bom_document_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rnd_project_bom_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('quantity', 15, 4);
            $table->string('unit', 50);
            $table->string('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rnd_bom_document_materials');
    }
};
