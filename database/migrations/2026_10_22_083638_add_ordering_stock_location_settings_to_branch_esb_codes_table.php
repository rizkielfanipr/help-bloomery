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
            $table->string('ordering_stock_location_mode', 20)->default('unconfigured')->after('is_active');
            $table->json('ordering_stock_locations')->nullable()->after('ordering_stock_location_mode');
            $table->timestamp('ordering_stock_locations_updated_at')->nullable()->after('ordering_stock_locations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branch_esb_codes', function (Blueprint $table): void {
            $table->dropColumn([
                'ordering_stock_location_mode',
                'ordering_stock_locations',
                'ordering_stock_locations_updated_at',
            ]);
        });
    }
};
