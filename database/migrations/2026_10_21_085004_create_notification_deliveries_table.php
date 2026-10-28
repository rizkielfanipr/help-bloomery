<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §14.3, §19.5: delivery log for external notifications (Telegram). `idempotency_key` is unique
 * so one event is delivered at most once; only a destination key is stored, never a token or
 * chat ID.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('channel', 20);
            $table->string('event_type', 60);
            $table->string('aggregate_type', 60);
            $table->unsignedBigInteger('aggregate_id');
            $table->string('destination_key', 60);
            $table->string('idempotency_key', 191)->unique();
            $table->string('status', 20);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('error_summary', 500)->nullable();
            $table->timestamps();

            $table->index(['aggregate_type', 'aggregate_id'], 'notification_deliveries_aggregate_index');
            $table->index(['status', 'created_at'], 'notification_deliveries_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
