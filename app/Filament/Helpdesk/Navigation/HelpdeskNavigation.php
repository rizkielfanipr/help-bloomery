<?php

namespace App\Filament\Helpdesk\Navigation;

use Closure;

final class HelpdeskNavigation
{
    /**
     * @return list<array{label: string, icon: string, perm: string, href: string, active: bool}>
     */
    public static function technicianItems(Closure $route, Closure $active): array
    {
        return [
            ['label' => 'Permintaan Service', 'icon' => 'clipboard-list', 'perm' => 'view service requests', 'href' => $route('filament.helpdesk.resources.service-requests.index'), 'active' => $active($route('filament.helpdesk.resources.service-requests.index'))],
            ['label' => 'Rekap Maintenance', 'icon' => 'calendar-cog', 'perm' => 'view technician monthly maintenance', 'href' => $route('filament.helpdesk.resources.technician-monthly-maintenances.index'), 'active' => $active($route('filament.helpdesk.resources.technician-monthly-maintenances.index'))],
            ['label' => 'Checklist Poin', 'icon' => 'list-checks', 'perm' => 'view technician monthly maintenance', 'href' => $route('filament.helpdesk.resources.technician-maintenance-checklists.index'), 'active' => $active($route('filament.helpdesk.resources.technician-maintenance-checklists.index'))],
            ['label' => 'Pengaturan', 'icon' => 'settings', 'perm' => 'edit service requests', 'href' => $route('filament.helpdesk.pages.technician-settings'), 'active' => $active($route('filament.helpdesk.pages.technician-settings'))],
        ];
    }
}
