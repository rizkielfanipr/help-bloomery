<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->date('sales_assessment_started_at')->nullable();
            $table->json('sales_assessment_excluded_dates')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['sales_assessment_started_at', 'sales_assessment_excluded_dates']);
        });
    }
};
