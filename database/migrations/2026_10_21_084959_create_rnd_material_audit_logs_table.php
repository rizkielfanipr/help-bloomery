<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §19.2, §27: append-only audit of workflow events and technical field changes (who, when, what,
 * before/after, reason). Values are sanitized before they are stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_material_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_product_esb_material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rnd_material_review_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 60);
            $table->string('field_path', 120)->nullable();
            $table->json('before_value')->nullable();
            $table->json('after_value')->nullable();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['rnd_product_esb_material_id', 'created_at'], 'rnd_material_audit_material_created_index');
            $table->index(['event', 'created_at'], 'rnd_material_audit_event_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_material_audit_logs');
    }
};
