<?php

namespace App\Actions\Rnd\Project;

use App\Enums\RndProjectType;
use App\Models\RndProject;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class RemovePremixAssemblyAction
{
    public function execute(User $actor, RndProject $project, int $bomId): void
    {
        Gate::forUser($actor)->authorize('edit rnd projects');
        abort_unless($project->project_type === RndProjectType::Premix, 422);
        $project->premixAssemblies()->findOrFail($bomId)->delete();
    }
}
