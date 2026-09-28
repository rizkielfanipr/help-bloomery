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
        Schema::table('rnd_projects', function (Blueprint $table): void {
            $table->decimal('forecast_percentage', 5, 2)->default(100)->after('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rnd_projects', function (Blueprint $table): void {
            $table->dropColumn('forecast_percentage');
        });
    }
};
