<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §12, §19.1: one row per IT review submission version with the canonical snapshot that was
 * submitted. `active_material_id` is only filled while the review is waiting/under review, so the
 * unique index allows at most one active review per material.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_material_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_product_esb_material_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('submission_version');
            $table->unsignedBigInteger('active_material_id')->nullable()->unique();
            $table->string('status', 30);
            $table->json('snapshot');
            $table->char('snapshot_hash', 64);
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('decision', 30)->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('linked_esb_product_id')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->unique(['rnd_product_esb_material_id', 'submission_version'], 'rnd_material_reviews_version_unique');
            $table->index(['status', 'submitted_at'], 'rnd_material_reviews_status_submitted_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_material_reviews');
    }
};
