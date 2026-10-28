<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/products/system-monitoring/prd.md §16: links between an incident and monitoring records (alert,
 * error, transaction, deployment). `linkable_type` is a short allow-listed key, not a class name,
 * and there is intentionally no polymorphic foreign key so pruning detail rows never cascades
 * into incident history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_incident_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('monitoring_incident_id')->constrained()->cascadeOnDelete();
            $table->string('linkable_type', 30);
            $table->unsignedBigInteger('linkable_id');
            $table->string('label')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['monitoring_incident_id', 'linkable_type', 'linkable_id'], 'monitoring_incident_links_unique');
            $table->index(['linkable_type', 'linkable_id'], 'monitoring_incident_links_linkable_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_incident_links');
    }
};
