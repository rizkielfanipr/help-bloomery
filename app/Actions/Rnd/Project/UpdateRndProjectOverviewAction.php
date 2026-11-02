<?php

namespace App\Actions\Rnd\Project;

use App\Models\RndProject;
use App\Models\User;
use App\Services\Rnd\RndRichContentService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UpdateRndProjectOverviewAction
{
    public const IMAGE_ALT = 'Gambar Project Overview';

    public function __construct(private readonly RndRichContentService $richContent) {}

    /**
     * Files uploaded through the editor are already on disk when this runs, so a failed save removes
     * the new ones and a successful save removes only those no longer referenced.
     */
    public function execute(User $actor, RndProject $project, ?string $html): RndProject
    {
        Gate::forUser($actor)->authorize('edit rnd projects');

        validator(
            ['overview' => $html],
            ['overview' => ['nullable', 'string', 'max:'.RndRichContentService::MAX_HTML_LENGTH]],
        )->validate();

        $prefix = $project->overviewAttachmentDirectory().'/';
        $previousHtml = (string) $project->newQueryWithoutScopes()->whereKey($project->getKey())->value('overview_html');
        $sanitizedHtml = $this->richContent->sanitize($html, $prefix, self::IMAGE_ALT);
        $newPaths = array_values(array_diff(
            $this->richContent->attachmentPaths((string) $sanitizedHtml, $prefix),
            $this->richContent->attachmentPaths($previousHtml, $prefix),
        ));

        try {
            $project->update(['overview_html' => $sanitizedHtml]);
        } catch (Throwable $exception) {
            $this->deleteAttachments($newPaths, $project, $actor, 'failed_save_cleanup');
            Log::error('R&D project overview save failed', [
                'project_id' => $project->id,
                'actor_id' => $actor->id,
                'outcome' => 'previous_content_kept',
            ]);

            throw $exception;
        }

        $this->deleteAttachments(
            $this->richContent->orphanedAttachmentPaths($previousHtml, (string) $sanitizedHtml, $prefix),
            $project,
            $actor,
            'orphan_cleanup',
        );

        return $project;
    }

    /**
     * A cleanup failure must not undo a save that already succeeded or hide the original error.
     *
     * @param  list<string>  $paths
     */
    private function deleteAttachments(array $paths, RndProject $project, User $actor, string $operation): void
    {
        if ($paths === []) {
            return;
        }

        try {
            $deleted = Storage::disk('b2')->delete($paths);
        } catch (Throwable) {
            $deleted = false;
        }

        if (! $deleted) {
            Log::warning('R&D project overview attachment cleanup failed', [
                'project_id' => $project->id,
                'actor_id' => $actor->id,
                'operation' => $operation,
                'file_count' => count($paths),
            ]);
        }
    }
}
