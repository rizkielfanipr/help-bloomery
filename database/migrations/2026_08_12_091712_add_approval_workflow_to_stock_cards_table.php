<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_cards', function (Blueprint $table) {
            $table->string('status', 30)->default('draft')->after('flag_unit');
            $table->foreignId('reviewed_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');
            $table->unsignedInteger('revision_number')->default(0)->after('review_note');
        });

        // Backfill: existing submitted cards had no review step at all, so
        // treat them as awaiting the (newly introduced) Supervisor review.
        DB::table('stock_cards')->whereNotNull('submitted_at')->update(['status' => 'pending_supervisor']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['status', 'reviewed_at', 'review_note', 'revision_number']);
        });
    }
};
