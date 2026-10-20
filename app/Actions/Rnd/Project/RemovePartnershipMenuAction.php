<?php

namespace App\Actions\Rnd\Project;

use App\Enums\RndProjectType;
use App\Models\RndProject;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class RemovePartnershipMenuAction
{
    public function execute(User $actor, RndProject $project, int $menuId): void
    {
        Gate::forUser($actor)->authorize('edit rnd projects');
        abort_unless($project->project_type === RndProjectType::Partnership, 422);
        $project->partnershipMenus()->findOrFail($menuId)->delete();
    }
}
