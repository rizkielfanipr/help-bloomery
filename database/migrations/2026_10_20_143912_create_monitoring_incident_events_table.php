<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/products/system-monitoring/prd.md §18.5: append-only incident timeline. There is no updated_at
 * column because events are never edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_incident_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('monitoring_incident_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['monitoring_incident_id', 'created_at'], 'monitoring_incident_events_incident_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_incident_events');
    }
};
