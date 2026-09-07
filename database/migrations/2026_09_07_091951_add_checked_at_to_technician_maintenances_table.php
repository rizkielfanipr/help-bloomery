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
        Schema::table('technician_maintenances', function (Blueprint $table): void {
            $table->date('checked_at')->nullable()->after('technician_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('technician_maintenances', function (Blueprint $table): void {
            $table->dropColumn('checked_at');
        });
    }
};
