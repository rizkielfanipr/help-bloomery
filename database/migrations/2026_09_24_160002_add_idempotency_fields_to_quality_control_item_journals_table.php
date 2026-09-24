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
        Schema::table('quality_control_item_journals', function (Blueprint $table): void {
            $table->uuid('submission_key')->nullable()->unique()->after('status');
            $table->char('payload_hash', 64)->nullable()->index()->after('submission_key');
            $table->timestamp('attempted_at')->nullable()->after('payload_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quality_control_item_journals', function (Blueprint $table): void {
            $table->dropUnique(['submission_key']);
            $table->dropIndex(['payload_hash']);
            $table->dropColumn(['submission_key', 'payload_hash', 'attempted_at']);
        });
    }
};
