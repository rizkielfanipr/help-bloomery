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
        Schema::create('helpdesk_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('helpdesk_form_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->nullOnDelete()->constrained('departments');
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('assignee_id')->nullable()->nullOnDelete()->constrained('users');
            $table->string('status')->default('draft');
            $table->json('data')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('helpdesk_requests');
    }
};
