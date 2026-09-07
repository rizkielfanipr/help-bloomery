<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_maintenance_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('technician_maintenance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_id')->nullable()->constrained('technician_maintenance_checklists')->nullOnDelete();
            $table->string('section_code', 30);
            $table->string('section_name');
            $table->text('question');
            $table->text('check_procedure')->nullable();
            $table->unsignedSmallInteger('maximum_points')->default(1);
            $table->unsignedSmallInteger('earned_points')->default(0);
            $table->boolean('is_critical')->default(false);
            $table->boolean('requires_photo')->default(false);
            $table->string('result')->nullable();
            $table->text('notes')->nullable();
            $table->json('photo_paths')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['technician_maintenance_id', 'checklist_id'], 'technician_maintenance_checklist_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_maintenance_items');
    }
};
