<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `actual_qty` becomes the "working" value a Supervisor can correct
     * during review — `reported_qty` preserves what the staff originally
     * submitted, snapshotted once when the entry is first finalized.
     */
    public function up(): void
    {
        Schema::table('stock_card_entries', function (Blueprint $table) {
            $table->decimal('reported_qty', 20, 4)->nullable()->after('actual_qty');
            $table->text('supervisor_notes')->nullable()->after('notes');
        });

        DB::table('stock_card_entries')->whereNotNull('actual_qty')->update(['reported_qty' => DB::raw('actual_qty')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_card_entries', function (Blueprint $table) {
            $table->dropColumn(['reported_qty', 'supervisor_notes']);
        });
    }
};
