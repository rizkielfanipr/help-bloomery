<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_request_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach (['Ticketing', 'Project', 'CMS'] as $index => $name) {
            DB::table('it_request_types')->insert([
                'name' => $name,
                'sort_order' => $index + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('erp_repair_requests', function (Blueprint $table): void {
            $table->string('ticket_number', 9)->nullable()->unique()->after('id');
            $table->foreignId('request_type_id')->nullable()->after('erp_module_id')->nullOnDelete()->constrained('it_request_types');
            $table->string('work_classification', 20)->nullable()->after('status');
            $table->string('priority', 20)->default('medium')->after('work_classification');
            $table->dateTime('due_at')->nullable()->after('priority');
            $table->text('it_notes')->nullable()->after('due_at');
            $table->string('escalation_target')->nullable()->after('it_notes');
            $table->text('escalation_reason')->nullable()->after('escalation_target');
            $table->dateTime('escalated_at')->nullable()->after('escalation_reason');
            $table->text('resolution_note')->nullable()->after('escalated_at');
            $table->foreignId('closed_by')->nullable()->after('resolution_note')->nullOnDelete()->constrained('users');
        });

        DB::table('erp_repair_requests')->orderBy('id')->each(function (object $request): void {
            DB::table('erp_repair_requests')->where('id', $request->id)->update([
                'ticket_number' => 'IT-'.str_pad((string) $request->id, 6, '0', STR_PAD_LEFT),
                'status' => match ($request->status) {
                    'draft' => 'submitted',
                    'approved' => 'in_progress',
                    'rejected' => 'cancelled',
                    default => $request->status,
                },
            ]);
        });

        Schema::create('it_request_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('erp_repair_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->nullOnDelete()->constrained('users');
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_request_activities');
        Schema::table('erp_repair_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('request_type_id');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn([
                'ticket_number', 'work_classification', 'priority', 'due_at', 'it_notes',
                'escalation_target', 'escalation_reason', 'escalated_at', 'resolution_note',
            ]);
        });
        Schema::dropIfExists('it_request_types');
    }
};
