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
        Schema::table('technician_maintenance_checklists', function (Blueprint $table): void {
            $table->dropColumn(['section_code', 'section_name', 'points', 'is_critical']);
        });

        Schema::table('technician_maintenance_items', function (Blueprint $table): void {
            $table->dropColumn(['section_code', 'section_name', 'maximum_points', 'earned_points', 'is_critical']);
        });

        Schema::table('technician_maintenances', function (Blueprint $table): void {
            $table->dropColumn(['score', 'earned_points', 'maximum_points']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('technician_maintenance_checklists', function (Blueprint $table): void {
            $table->string('section_code', 30)->default('default');
            $table->string('section_name')->default('Checklist');
            $table->unsignedSmallInteger('points')->default(1);
            $table->boolean('is_critical')->default(false);
        });

        Schema::table('technician_maintenance_items', function (Blueprint $table): void {
            $table->string('section_code', 30)->default('default');
            $table->string('section_name')->default('Checklist');
            $table->unsignedSmallInteger('maximum_points')->default(1);
            $table->unsignedSmallInteger('earned_points')->default(0);
            $table->boolean('is_critical')->default(false);
        });

        Schema::table('technician_maintenances', function (Blueprint $table): void {
            $table->decimal('score', 6, 2)->default(0);
            $table->unsignedInteger('earned_points')->default(0);
            $table->unsignedInteger('maximum_points')->default(0);
        });
    }
};
