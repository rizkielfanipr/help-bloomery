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
        if (! Schema::hasTable('kitchen_production_schedules')) {
            return;
        }

        Schema::table('kitchen_production_schedules', function (Blueprint $table): void {
            $table->string('status', 20)->default('scheduled')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('kitchen_production_schedules')) {
            return;
        }

        Schema::table('kitchen_production_schedules', function (Blueprint $table): void {
            $table->string('status', 20)->default('draft')->change();
        });
    }
};
