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
 * Serves private Task attachments (instruction and follow-up result files) from the `b2` disk,
 * mirroring `RndBomInstructionImageController`'s authorization + strict path pattern instead of
 * ERP Request's unauthenticated inline presigned URL (docs/rnd-project-task-calendar-prd.md §20 —
 * see Phase 0 audit: the ERP pattern does not enforce per-file authorization).
 */
class RndProjectTaskAttachmentController extends Controller
{
    public function show(Request $request, string $path): Response
    {
        abort_unless(
            preg_match('#^rnd/project-tasks/(\d+)/assignments/(\d+)/results/[^/]+\.\w+$#i', $path, $matches) === 1,
            404,
        );

        $task = RndProjectTask::query()->findOrFail((int) $matches[1]);
        RndProjectTaskAssignment::query()
            ->where('rnd_project_task_id', $task->id)
            ->findOrFail((int) $matches[2]);

        abort_unless($request->user()?->can('view', $task), 403);
        abort_unless(Storage::disk('b2')->exists($path), 404);

        try {
            return Storage::disk('b2')->response($path, null, ['Cache-Control' => 'private, max-age=3600']);
        } catch (Throwable) {
            abort(404);
        }
    }
}
