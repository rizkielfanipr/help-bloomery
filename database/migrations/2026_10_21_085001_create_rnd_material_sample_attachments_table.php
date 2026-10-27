<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §16, §23: private hand-over (Purchasing) and receipt (R&D) photos kept as separate evidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_material_sample_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_material_sample_cycle_id')->constrained(indexName: 'rnd_material_sample_attachments_cycle_fk')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('disk', 40);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->index(['rnd_material_sample_cycle_id', 'kind'], 'rnd_material_sample_attachments_cycle_kind_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_material_sample_attachments');
    }
};
