<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the old service_requests table and service_templates table
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('service_templates');

        // Recreate service_requests with new simplified structure
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->foreignId('technician_id')->nullable()->nullOnDelete()->constrained('users');
            $table->foreignId('scheduled_by')->constrained('users')->cascadeOnDelete();
            $table->date('scheduled_date');
            $table->text('requestor_notes')->nullable();
            $table->json('attachments')->nullable();
            $table->string('status')->default('submitted');
            $table->string('before_photo')->nullable();
            $table->text('before_notes')->nullable();
            $table->string('after_photo')->nullable();
            $table->text('after_notes')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('warranty_expires_at')->nullable();
            $table->timestamps();
        });

        // Technician settings table
        Schema::create('technician_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('max_jobs_per_day')->default(5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_settings');
        Schema::dropIfExists('service_requests');

        // Restore old service_templates
        Schema::create('service_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Restore old service_requests
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_template_id')->nullable()->nullOnDelete()->constrained('service_templates');
            $table->foreignId('technician_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('scheduled_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('submitted');
            $table->dateTime('scheduled_at')->nullable();
            $table->string('before_photo')->nullable();
            $table->text('before_notes')->nullable();
            $table->string('after_photo')->nullable();
            $table->text('after_notes')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('warranty_expires_at')->nullable();
            $table->timestamps();
        });
    }
};
