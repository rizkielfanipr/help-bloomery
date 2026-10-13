<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/daily-briefing-bundle-approval-prd.md §7.3/§9.3/§10.2: each item records its own
     * contributor (several STORE_STAFF can fill one branch bundle) and can be flagged for
     * correction when its bundle is rejected. The legacy per-item review columns stay untouched
     * through the compatibility phase.
     */
    public function up(): void
    {
        Schema::table('briefing_items', function (Blueprint $table) {
            $table->foreignId('submitted_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->boolean('requires_correction')->default(false)->after('rejection_reason');
            $table->text('correction_note')->nullable()->after('requires_correction');
        });
    }

    public function down(): void
    {
        Schema::table('briefing_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn(['submitted_at', 'requires_correction', 'correction_note']);
        });
    }
};
