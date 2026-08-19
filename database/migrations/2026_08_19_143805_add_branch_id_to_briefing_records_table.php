<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('briefing_records', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();
        });

        DB::table('briefing_records')
            ->select(['id', 'user_id'])
            ->whereNull('branch_id')
            ->orderBy('id')
            ->chunkById(500, function ($records): void {
                $recordIds = $records->pluck('id');
                $branchIdsFromTasks = DB::table('briefing_items')
                    ->join('briefing_tasks', 'briefing_tasks.key', '=', 'briefing_items.task_key')
                    ->whereIn('briefing_items.briefing_record_id', $recordIds)
                    ->whereNotNull('briefing_tasks.branch_id')
                    ->selectRaw('briefing_items.briefing_record_id, MIN(briefing_tasks.branch_id) as branch_id')
                    ->groupBy('briefing_items.briefing_record_id')
                    ->pluck('branch_id', 'briefing_record_id');
                $branchIds = DB::table('users')
                    ->whereIn('id', $records->pluck('user_id'))
                    ->pluck('branch_id', 'id');

                foreach ($records as $record) {
                    DB::table('briefing_records')
                        ->where('id', $record->id)
                        ->update([
                            'branch_id' => $branchIdsFromTasks[$record->id]
                                ?? $branchIds[$record->user_id]
                                ?? null,
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('briefing_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
