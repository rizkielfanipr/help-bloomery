<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('erp_repair_requests')
            ->whereIn('status', ['waiting_user', 'escalated'])
            ->update(['status' => 'in_progress']);

        DB::table('erp_repair_requests')
            ->where('status', 'cancelled')
            ->update(['status' => 'rejected']);

        Schema::table('erp_repair_requests', function (Blueprint $table): void {
            $table->dropColumn(['escalation_target', 'escalation_reason', 'escalated_at', 'resolution_note']);
        });
    }

    public function down(): void
    {
        Schema::table('erp_repair_requests', function (Blueprint $table): void {
            $table->string('escalation_target')->nullable()->after('it_notes');
            $table->text('escalation_reason')->nullable()->after('escalation_target');
            $table->dateTime('escalated_at')->nullable()->after('escalation_reason');
            $table->text('resolution_note')->nullable()->after('escalated_at');
        });
    }
};
