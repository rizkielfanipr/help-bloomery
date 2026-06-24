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
        Schema::table('briefing_items', function (Blueprint $table) {
            $table->string('review_status')->nullable()->after('completed_at');
            $table->text('rejection_reason')->nullable()->after('review_status');
        });
    }

    public function down(): void
    {
        Schema::table('briefing_items', function (Blueprint $table) {
            $table->dropColumn(['review_status', 'rejection_reason']);
        });
    }
};
