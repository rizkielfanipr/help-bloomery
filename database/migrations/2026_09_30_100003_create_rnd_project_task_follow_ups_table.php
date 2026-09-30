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
        if (Schema::hasTable('rnd_project_task_follow_ups')) {
            $this->repairTableLeftByFailedMigration();

            return;
        }

        Schema::create('rnd_project_task_follow_ups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_project_task_assignment_id')->constrained(
                table: 'rnd_project_task_assignments',
                indexName: 'rnd_task_followup_assignment_fk',
            )->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('follow_up_type', 20);
            $table->text('notes')->nullable();
            $table->date('estimated_completion_date')->nullable();
            $table->json('result_attachments')->nullable();
            $table->timestamps();

            $table->index('rnd_project_task_assignment_id');
            $table->index('follow_up_type');
        });
    }

    /**
     * MySQL may keep the CREATE TABLE result when a later ALTER TABLE for an automatically
     * generated foreign-key name fails. Complete that partial table without deleting its data.
     */
    private function repairTableLeftByFailedMigration(): void
    {
        $foreignColumns = collect(Schema::getForeignKeys('rnd_project_task_follow_ups'))
            ->flatMap(fn (array $foreignKey): array => $foreignKey['columns'])
            ->all();
        $indexedColumns = collect(Schema::getIndexes('rnd_project_task_follow_ups'))
            ->flatMap(fn (array $index): array => $index['columns'])
            ->all();

        if (! in_array('rnd_project_task_assignment_id', $indexedColumns, true)) {
            Schema::table('rnd_project_task_follow_ups', function (Blueprint $table): void {
                $table->index('rnd_project_task_assignment_id', 'rnd_task_followup_assignment_idx');
            });
        }

        if (! in_array('follow_up_type', $indexedColumns, true)) {
            Schema::table('rnd_project_task_follow_ups', function (Blueprint $table): void {
                $table->index('follow_up_type', 'rnd_task_followup_type_idx');
            });
        }

        if (! in_array('rnd_project_task_assignment_id', $foreignColumns, true)) {
            Schema::table('rnd_project_task_follow_ups', function (Blueprint $table): void {
                $table->foreign('rnd_project_task_assignment_id', 'rnd_task_followup_assignment_fk')
                    ->references('id')
                    ->on('rnd_project_task_assignments')
                    ->cascadeOnDelete();
            });
        }

        if (! in_array('submitted_by', $foreignColumns, true)) {
            Schema::table('rnd_project_task_follow_ups', function (Blueprint $table): void {
                $table->foreign('submitted_by', 'rnd_task_followup_submitter_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rnd_project_task_follow_ups');
    }
};
