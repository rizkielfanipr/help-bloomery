<?php

namespace App\Services;

use Filament\Facades\Filament;

class PermissionRegistry
{
    /** @return array<string, array<string, array<int, string>>> */
    public function groups(): array
    {
        $groups = config('permissions', []);

        foreach (Filament::getPanels() as $panel) {
            foreach ($panel->getResources() as $resource) {
                if (! method_exists($resource, 'permissionDefinition')) {
                    continue;
                }

                $definition = $resource::permissionDefinition();

                if (! is_array($definition)) {
                    continue;
                }

                $group = $definition['group'] ?? null;
                $label = $definition['label'] ?? null;
                $permissions = $definition['permissions'] ?? null;

                if (! is_string($group) || ! is_string($label) || ! is_array($permissions)) {
                    continue;
                }

                $registeredPermissions = collect($groups)
                    ->flatMap(fn (array $resources): array => array_merge(...array_values($resources)));

                if (collect($permissions)->every(
                    fn (string $permission): bool => $registeredPermissions->contains($permission)
                )) {
                    continue;
                }

                // Explicit config wins when a feature needs non-standard actions.
                $groups[$group][$label] ??= $permissions;
            }
        }

        return $groups;
    }
}
