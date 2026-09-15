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
        Schema::table('vendor_compliance_incidents', function (Blueprint $table) {
            $table->string('action_type')->nullable()->after('status');
            $table->text('follow_up_notes')->nullable()->after('action_type');
            $table->foreignId('handled_by')->nullable()->after('follow_up_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable()->after('handled_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_compliance_incidents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handled_by');
            $table->dropColumn(['action_type', 'follow_up_notes', 'resolved_at']);
        });
    }
};
