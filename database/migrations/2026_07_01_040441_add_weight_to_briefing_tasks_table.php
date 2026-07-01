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
        Schema::table('briefing_tasks', function (Blueprint $table) {
            $table->decimal('weight', 5, 2)->nullable()->after('is_active')->comment('Bobot poin dalam penilaian (0-100). Null = tidak masuk penilaian.');
        });
    }

    public function down(): void
    {
        Schema::table('briefing_tasks', function (Blueprint $table) {
            $table->dropColumn('weight');
        });
    }
};
