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
        Schema::table('customer_complaints', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropForeign(['resolved_by']);
            $table->dropForeign(['closed_by']);
            $table->dropIndex(['assigned_to']);
            $table->dropColumn([
                'assigned_to',
                'internal_notes',
                'resolution',
                'resolved_at',
                'resolved_by',
                'closed_at',
                'closed_by',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_complaints', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->text('internal_notes')->nullable()->after('resolution');
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }
};
