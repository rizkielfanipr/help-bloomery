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
        Schema::create('quality_control_audits', function (Blueprint $table) {
            $table->id();
            $table->string('audit_number')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auditor_id')->constrained('users')->cascadeOnDelete();
            $table->date('audit_date');
            $table->string('audit_type')->default('routine');
            $table->string('store_leader_name')->nullable();
            $table->boolean('store_leader_present')->default(false);
            $table->string('status')->default('draft');
            $table->decimal('score', 6, 2)->default(0);
            $table->unsignedInteger('earned_points')->default(0);
            $table->unsignedInteger('maximum_points')->default(0);
            $table->string('rating', 20)->nullable();
            $table->text('top_findings')->nullable();
            $table->text('corrective_action_required')->nullable();
            $table->text('overall_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'audit_date']);
            $table->index(['status', 'rating']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_control_audits');
    }
};
