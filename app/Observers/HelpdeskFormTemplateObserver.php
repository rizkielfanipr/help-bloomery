<?php

namespace App\Observers;

use App\Models\HelpdeskFormTemplate;
use Spatie\Permission\Models\Permission;

class HelpdeskFormTemplateObserver
{
    public function created(HelpdeskFormTemplate $template): void
    {
        $this->syncPermissions($template);
    }

    public function updated(HelpdeskFormTemplate $template): void
    {
        $this->syncPermissions($template);
    }

    public function forceDeleted(HelpdeskFormTemplate $template): void
    {
        Permission::where('name', 'like', "helpdesk.{$template->id}.%")->delete();
    }

    private function syncPermissions(HelpdeskFormTemplate $template): void
    {
        foreach (['create', 'read', 'update', 'delete'] as $action) {
            Permission::firstOrCreate(
                ['name' => "helpdesk.{$template->id}.{$action}", 'guard_name' => 'web']
            );
        }
    }
}
