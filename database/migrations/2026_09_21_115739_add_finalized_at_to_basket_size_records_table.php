<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A record is final once it was calculated from the complete shift window.
     * Records that already exist keep their values, so they are treated as final.
     */
    public function up(): void
    {
        Schema::table('basket_size_records', function (Blueprint $table): void {
            $table->timestamp('finalized_at')->nullable()->after('calculated_at');
        });

        DB::table('basket_size_records')->update(['finalized_at' => DB::raw('coalesce(calculated_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('basket_size_records', function (Blueprint $table): void {
            $table->dropColumn('finalized_at');
        });
    }
};
