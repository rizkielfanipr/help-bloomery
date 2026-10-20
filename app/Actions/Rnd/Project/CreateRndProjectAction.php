<?php

namespace App\Actions\Rnd\Project;

use App\Enums\RndProjectType;
use App\Models\RndProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateRndProjectAction
{
    /** @param array{name: string, description?: ?string, start_date: string, end_date: string, project_type: RndProjectType, partnership_event_name?: ?string, partnership_branch_ids?: list<int>} $attributes */
    public function execute(User $actor, array $attributes): RndProject
    {
        Gate::forUser($actor)->authorize('create rnd projects');

        return DB::transaction(function () use ($actor, $attributes): RndProject {
            $project = RndProject::query()->create([
                'name' => trim($attributes['name']),
                'description' => filled($attributes['description'] ?? null) ? trim((string) $attributes['description']) : null,
                'start_date' => $attributes['start_date'],
                'end_date' => $attributes['end_date'],
                'project_type' => $attributes['project_type'],
                'partnership_event_name' => $attributes['project_type'] === RndProjectType::Partnership
                    ? trim((string) ($attributes['partnership_event_name'] ?? ''))
                    : null,
                'created_by' => $actor->id,
            ]);
            $project->partnershipBranches()->sync(
                $attributes['project_type'] === RndProjectType::Partnership
                    ? ($attributes['partnership_branch_ids'] ?? [])
                    : [],
            );

            return $project->refresh();
        });
    }
}
