<?php

namespace App\Actions\Rnd\Project;

use App\Models\RndProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateRndProjectAction
{
    /** @param array{name: string, description?: ?string, start_date: string, end_date: string, partnership_event_name?: ?string, partnership_branch_ids?: list<int>} $attributes */
    public function execute(User $actor, RndProject $project, array $attributes): RndProject
    {
        Gate::forUser($actor)->authorize('edit rnd projects');

        return DB::transaction(function () use ($project, $attributes): RndProject {
            $project->update([
                'name' => trim($attributes['name']),
                'description' => filled($attributes['description'] ?? null) ? trim((string) $attributes['description']) : null,
                'start_date' => $attributes['start_date'],
                'end_date' => $attributes['end_date'],
                'partnership_event_name' => $project->project_type->value === 'partnership'
                    ? trim((string) ($attributes['partnership_event_name'] ?? $project->partnership_event_name))
                    : null,
            ]);
            $project->partnershipBranches()->sync(
                $project->project_type->value === 'partnership'
                    ? ($attributes['partnership_branch_ids'] ?? [])
                    : [],
            );

            return $project->refresh();
        });
    }
}
