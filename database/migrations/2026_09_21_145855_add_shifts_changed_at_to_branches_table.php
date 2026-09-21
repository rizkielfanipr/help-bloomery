<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the Shift Basket Size settings of a branch last changed. Basket size that was
     * calculated before this moment no longer matches the current shift hours.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->timestamp('shifts_changed_at')->nullable()->after('sales_shift_count');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn('shifts_changed_at');
        });
    }
};
