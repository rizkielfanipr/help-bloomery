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
        Schema::table('briefing_scores', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->change();
        });

        Schema::table('sales_reports', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('briefing_scores', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable(false)->change();
        });

        Schema::table('sales_reports', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable(false)->change();
        });
    }
};
