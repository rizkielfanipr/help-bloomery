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
        if (Schema::hasColumn('assets', 'location_id')) {
            Schema::table('assets', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('location_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->foreignId('location_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
        });
    }
};
