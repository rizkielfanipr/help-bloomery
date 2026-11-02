<?php

namespace App\Actions;

use App\Models\RndProject;

class RestoreRndProjectAction
{
    public function execute(RndProject $project): void
    {
        $project->restore();
    }
}
