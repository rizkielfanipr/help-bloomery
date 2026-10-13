<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/daily-briefing-bundle-approval-prd.md §10.3: append-only audit of every bundle status
     * change — who, when, why, and whether it came from a user, a data migration, or a system
     * reconciliation.
     */
    public function up(): void
    {
        Schema::create('briefing_record_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('briefing_record_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->string('source');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['briefing_record_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('briefing_record_transitions');
    }
};
