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
        Schema::table('branch_esb_codes', function (Blueprint $table): void {
            $table->timestamp('esb_synced_at')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branch_esb_codes', function (Blueprint $table): void {
            $table->dropColumn('esb_synced_at');
        });
    }
};
