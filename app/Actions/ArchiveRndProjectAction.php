<?php

namespace App\Actions;

use App\Models\RndProject;

class ArchiveRndProjectAction
{
    public function execute(RndProject $project): void
    {
        $project->delete();
    }
}
