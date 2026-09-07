<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_maintenance_checklists', function (Blueprint $table): void {
            $table->id();
            $table->string('section_code', 30);
            $table->string('section_name');
            $table->text('question');
            $table->text('check_procedure')->nullable();
            $table->unsignedSmallInteger('points')->default(1);
            $table->boolean('is_critical')->default(false);
            $table->boolean('requires_photo')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_maintenance_checklists');
    }
};
