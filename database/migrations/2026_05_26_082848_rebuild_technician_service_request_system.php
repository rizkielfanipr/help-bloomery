<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove technician workflow columns added to helpdesk tables
        Schema::table('helpdesk_form_templates', function (Blueprint $table) {
            $table->dropColumn('is_technician_workflow');
        });

        Schema::table('helpdesk_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('technician_id');
            $table->dropColumn([
                'scheduled_at', 'technician_status',
                'before_photo', 'before_notes',
                'after_photo', 'after_notes',
                'started_at', 'completed_at', 'warranty_expires_at',
            ]);
        });

        // Dedicated technician service tables
        Schema::create('service_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

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

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('service_templates');
    }
};
