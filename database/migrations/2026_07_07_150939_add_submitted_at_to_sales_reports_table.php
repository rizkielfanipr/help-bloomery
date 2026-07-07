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
        Schema::table('sales_reports', function (Blueprint $table): void {
            $table->timestamp('shift_1_submitted_at')->nullable()->after('modal_shift_1');
            $table->timestamp('shift_2_submitted_at')->nullable()->after('modal_shift_2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_reports', function (Blueprint $table): void {
            $table->dropColumn(['shift_1_submitted_at', 'shift_2_submitted_at']);
        });
    }
};
