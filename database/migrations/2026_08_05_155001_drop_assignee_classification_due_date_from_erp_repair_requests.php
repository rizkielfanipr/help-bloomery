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
            $table->dropForeign(['assignee_id']);
            $table->dropColumn(['assignee_id', 'work_classification', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('erp_repair_requests', function (Blueprint $table) {
            $table->foreignId('assignee_id')->nullable()->nullOnDelete()->constrained('users');
            $table->string('work_classification', 20)->nullable();
            $table->dateTime('due_at')->nullable();
        });
    }
};
