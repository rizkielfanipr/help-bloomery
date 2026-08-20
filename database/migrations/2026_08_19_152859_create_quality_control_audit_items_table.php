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
        Schema::create('quality_control_audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_control_audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quality_control_checklist_item_id')->nullable()->constrained(indexName: 'qc_audit_items_checklist_item_id_foreign')->nullOnDelete();
            $table->string('section_code', 10);
            $table->string('section_name');
            $table->text('question');
            $table->text('check_procedure')->nullable();
            $table->unsignedSmallInteger('maximum_points')->default(0);
            $table->unsignedSmallInteger('earned_points')->default(0);
            $table->boolean('is_critical')->default(false);
            $table->boolean('requires_photo')->default(false);
            $table->string('result')->nullable();
            $table->text('notes')->nullable();
            $table->json('evidence_photos')->nullable();
            $table->text('corrective_action')->nullable();
            $table->foreignId('action_pic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('action_due_date')->nullable();
            $table->string('action_status')->default('open');
            $table->json('action_evidence_photos')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['quality_control_audit_id', 'quality_control_checklist_item_id'], 'qc_audit_checklist_unique');
            $table->index(['quality_control_audit_id', 'section_code'], 'qc_audit_item_section_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_control_audit_items');
    }
};
