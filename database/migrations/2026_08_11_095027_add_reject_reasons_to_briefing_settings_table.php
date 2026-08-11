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
        Schema::table('briefing_settings', function (Blueprint $table) {
            $table->string('auto_reject_reason')->default('Tidak ada approval dalam :days hari setelah poin diselesaikan.');
            $table->string('deadline_reject_reason')->default('Tidak ada input sebelum deadline.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('briefing_settings', function (Blueprint $table) {
            $table->dropColumn(['auto_reject_reason', 'deadline_reject_reason']);
        });
    }
};
