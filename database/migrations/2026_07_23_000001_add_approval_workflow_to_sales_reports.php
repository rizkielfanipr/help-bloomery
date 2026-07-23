<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_reports', function (Blueprint $table): void {
            $table->string('status', 40)->default('draft')->after('submitted_at')->index();
            $table->foreignId('supervisor_reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('supervisor_reviewed_at')->nullable()->after('supervisor_reviewed_by');
            $table->text('supervisor_note')->nullable()->after('supervisor_reviewed_at');
            $table->foreignId('finance_reviewed_by')->nullable()->after('supervisor_note')->constrained('users')->nullOnDelete();
            $table->timestamp('finance_reviewed_at')->nullable()->after('finance_reviewed_by');
            $table->text('finance_note')->nullable()->after('finance_reviewed_at');
            $table->text('rejection_reason')->nullable()->after('finance_note');
            $table->unsignedInteger('revision_number')->default(0)->after('rejection_reason');
        });

        DB::table('sales_reports')->whereNotNull('submitted_at')->update(['status' => 'pending_supervisor']);

        Schema::table('sales_report_entries', function (Blueprint $table): void {
            $table->decimal('settlement_amount', 15, 2)->nullable()->after('sales_store_amount');
            $table->decimal('mdr_percentage', 8, 4)->nullable()->after('settlement_amount');
            $table->decimal('mdr_amount', 15, 2)->nullable()->after('mdr_percentage');
            $table->decimal('expected_settlement_amount', 15, 2)->nullable()->after('mdr_amount');
            $table->decimal('settlement_difference', 15, 2)->nullable()->after('expected_settlement_amount');
            $table->string('reconciliation_status', 30)->nullable()->after('settlement_difference');
            $table->text('finance_note')->nullable()->after('reconciliation_status');
        });

        Schema::create('sales_report_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_report_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 20);
            $table->string('action', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedInteger('revision_number')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['sales_report_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_report_approvals');

        Schema::table('sales_report_entries', function (Blueprint $table): void {
            $table->dropColumn([
                'settlement_amount', 'mdr_percentage', 'mdr_amount',
                'expected_settlement_amount', 'settlement_difference',
                'reconciliation_status', 'finance_note',
            ]);
        });

        Schema::table('sales_reports', function (Blueprint $table): void {
            $table->dropForeign(['supervisor_reviewed_by']);
            $table->dropForeign(['finance_reviewed_by']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status', 'supervisor_reviewed_by', 'supervisor_reviewed_at', 'supervisor_note',
                'finance_reviewed_by', 'finance_reviewed_at', 'finance_note',
                'rejection_reason', 'revision_number',
            ]);
        });
    }
};
