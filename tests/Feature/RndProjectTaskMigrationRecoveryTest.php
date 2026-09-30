<?php

use App\Models\RndProjectTaskAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('keeps existing follow-up data when the recovery migration is invoked again', function () {
    $assignment = RndProjectTaskAssignment::factory()->create();
    $submitter = User::factory()->create();
    $followUpId = DB::table('rnd_project_task_follow_ups')->insertGetId([
        'rnd_project_task_assignment_id' => $assignment->id,
        'submitted_by' => $submitter->id,
        'follow_up_type' => 'progress',
        'notes' => 'Data yang harus tetap tersimpan.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_09_30_100003_create_rnd_project_task_follow_ups_table.php');
    $migration->up();

    expect(DB::table('rnd_project_task_follow_ups')->where('id', $followUpId)->value('notes'))
        ->toBe('Data yang harus tetap tersimpan.');
});
