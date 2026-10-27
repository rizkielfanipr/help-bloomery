<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/operations/monitoring/prd.md §12: latest local heartbeat per process (scheduler, queue worker).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_heartbeats', function (Blueprint $table): void {
            $table->id();
            $table->string('environment', 30);
            $table->string('key', 60);
            $table->timestamp('last_beat_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['environment', 'key'], 'monitoring_heartbeats_env_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_heartbeats');
    }
};
