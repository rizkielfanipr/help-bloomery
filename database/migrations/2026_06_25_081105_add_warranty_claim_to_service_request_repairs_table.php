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
        Schema::table('service_request_repairs', function (Blueprint $table) {
            $table->text('warranty_claim_notes')->nullable()->after('warranty_expires_at');
            $table->json('warranty_claim_attachments')->nullable()->after('warranty_claim_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_request_repairs', function (Blueprint $table) {
            $table->dropColumn(['warranty_claim_notes', 'warranty_claim_attachments']);
        });
    }
};
