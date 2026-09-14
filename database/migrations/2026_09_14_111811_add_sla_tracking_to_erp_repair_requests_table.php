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
        Schema::table('erp_repair_requests', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('first_responded_at')->nullable();
            $table->unsignedBigInteger('response_business_seconds')->nullable();
            $table->unsignedBigInteger('resolution_business_seconds')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('erp_repair_requests', function (Blueprint $table) {
            $table->dropIndex(['submitted_at']);
            $table->dropColumn(['submitted_at', 'first_responded_at', 'response_business_seconds', 'resolution_business_seconds']);
        });
    }
};
