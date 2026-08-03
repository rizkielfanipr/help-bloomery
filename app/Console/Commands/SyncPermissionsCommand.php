<?php

namespace App\Console\Commands;

use App\Services\PermissionSynchronizer;
use Illuminate\Console\Command;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Create configured permissions and grant all of them to SUPERADMIN';

    public function handle(PermissionSynchronizer $synchronizer): int
    {
        $created = $synchronizer->sync();

        $this->info("Permissions synchronized. {$created} new permission(s) created.");

        return self::SUCCESS;
    }
}
