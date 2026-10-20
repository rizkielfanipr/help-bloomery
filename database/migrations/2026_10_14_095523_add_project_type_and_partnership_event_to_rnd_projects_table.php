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
            $table->string('project_type', 30)->default('release_product')->index()->after('description');
            $table->string('partnership_event_name')->nullable()->after('project_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rnd_projects', function (Blueprint $table): void {
            $table->dropIndex(['project_type']);
            $table->dropColumn(['project_type', 'partnership_event_name']);
        });
    }
};
