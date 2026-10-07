<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Serves private Task attachments (instruction and follow-up result files) from the Task attachment
 * disk (`RndProjectTask::attachmentDisk()`, `b2` by default),
 * mirroring `RndBomInstructionImageController`'s authorization + strict path pattern instead of
 * ERP Request's unauthenticated inline presigned URL (docs/rnd-project-task-calendar-prd.md §20 —
 * see Phase 0 audit: the ERP pattern does not enforce per-file authorization).
 */
class RndProjectTaskAttachmentController extends Controller
{
    public function show(Request $request, string $path): Response
    {
        $task = $this->resolveTaskFromPath($path);

        abort_unless($request->user()?->can('view', $task), 403);
        $disk = Storage::disk(RndProjectTask::attachmentDisk());

        abort_unless($disk->exists($path), 404);

        try {
            return $disk->response($path, null, ['Cache-Control' => 'private, max-age=3600']);
        } catch (Throwable) {
            abort(404);
        }
    }

    private function resolveTaskFromPath(string $path): RndProjectTask
    {
        if (preg_match('#^rnd/project-tasks/(\d+)/assignments/(\d+)/results/[^/]+\.\w+$#i', $path, $matches) === 1) {
            $task = RndProjectTask::query()->findOrFail((int) $matches[1]);
            $assignment = RndProjectTaskAssignment::query()
                ->where('rnd_project_task_id', $task->id)
                ->with('followUps:id,rnd_project_task_assignment_id,result_attachments')
                ->findOrFail((int) $matches[2]);
            $registeredPaths = $assignment->followUps
                ->flatMap(fn ($followUp) => $followUp->result_attachments ?? []);
            abort_unless($registeredPaths->containsStrict($path), 404);

            return $task;
        }

        if (preg_match('#^rnd/project-tasks/(\d+)/instructions/[^/]+\.\w+$#i', $path, $matches) === 1) {
            $task = RndProjectTask::query()->findOrFail((int) $matches[1]);
            abort_unless(in_array($path, $task->instruction_attachments ?? [], true), 404);

            return $task;
        }

        abort(404);
    }
}
