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
        Schema::create('quality_control_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->string('section_code', 10);
            $table->string('section_name');
            $table->text('question');
            $table->text('check_procedure')->nullable();
            $table->unsignedSmallInteger('points')->default(0);
            $table->boolean('is_critical')->default(false);
            $table->boolean('requires_photo')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'section_code', 'sort_order'], 'qc_checklist_active_section_sort_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_control_checklist_items');
    }
};
