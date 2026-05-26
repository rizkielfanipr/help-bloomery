<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Flag for templates that route to technician workflow
        Schema::table('helpdesk_form_templates', function (Blueprint $table) {
            $table->boolean('is_technician_workflow')->default(false)->after('is_active');
        });

        // Technician assignment + before/after work fields
        Schema::table('helpdesk_requests', function (Blueprint $table) {
            $table->foreignId('technician_id')->nullable()->nullOnDelete()->constrained('users')->after('assignee_id');
            $table->dateTime('scheduled_at')->nullable()->after('technician_id');
            $table->string('technician_status')->nullable()->after('scheduled_at'); // submitted|in_progress|warranty|completed
            $table->string('before_photo')->nullable()->after('technician_status');
            $table->text('before_notes')->nullable()->after('before_photo');
            $table->string('after_photo')->nullable()->after('before_notes');
            $table->text('after_notes')->nullable()->after('after_photo');
            $table->dateTime('started_at')->nullable()->after('after_notes');
            $table->dateTime('completed_at')->nullable()->after('started_at');
            $table->dateTime('warranty_expires_at')->nullable()->after('completed_at');
        });

        // Drop the standalone service request tables we no longer need
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('service_templates');
    }

    public function down(): void
    {
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
    }
};
