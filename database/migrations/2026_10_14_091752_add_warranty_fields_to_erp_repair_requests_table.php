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
        Schema::table('erp_repair_requests', function (Blueprint $table): void {
            $table->timestamp('warranty_started_at')->nullable()->after('resolved_at');
            $table->timestamp('warranty_expires_at')->nullable()->after('warranty_started_at')->index();
            $table->timestamp('warranty_claimed_at')->nullable()->after('warranty_expires_at');
            $table->text('warranty_claim_notes')->nullable()->after('warranty_claimed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('erp_repair_requests', function (Blueprint $table): void {
            $table->dropIndex(['warranty_expires_at']);
            $table->dropColumn(['warranty_started_at', 'warranty_expires_at', 'warranty_claimed_at', 'warranty_claim_notes']);
        });
    }
};
