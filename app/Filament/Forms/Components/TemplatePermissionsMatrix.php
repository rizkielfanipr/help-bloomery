<?php

namespace App\Filament\Forms\Components;

use App\Models\HelpdeskFormTemplate;
use Filament\Forms\Components\Field;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

class TemplatePermissionsMatrix extends Field
{
    protected string $view = 'filament.forms.components.template-permissions-matrix';

    /** @var array<string, string> */
    public static array $crudActions = [
        'create' => 'Buat',
        'read' => 'Lihat',
        'update' => 'Edit',
        'delete' => 'Hapus',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);

        $this->afterStateHydrated(function (TemplatePermissionsMatrix $component): void {
            $record = $component->getRecord();

            if (! $record) {
                $component->state([]);

                return;
            }

            $templatePermIds = $this->getTemplatePermissionIds();

            $component->state(
                $record->permissions
                    ->pluck('id')
                    ->intersect($templatePermIds)
                    ->values()
                    ->map(fn ($id) => (string) $id)
                    ->toArray()
            );
        });

        $this->saveRelationshipsUsing(function (TemplatePermissionsMatrix $component): void {
            $record = $component->getRecord();
            $state = array_map('intval', $component->getState() ?? []);

            $templatePermIds = $this->getTemplatePermissionIds();

            // Use fresh() to read state after the CheckboxList relationship has already saved.
            $otherPermIds = $record->fresh()->permissions->pluck('id')->diff($templatePermIds)->values()->toArray();

            $record->syncPermissions(
                Permission::whereIn('id', array_merge($otherPermIds, $state))->get()
            );
        });
    }

    public function getTemplates(): Collection
    {
        return HelpdeskFormTemplate::orderBy('name')->get();
    }

    public function getPermissionId(int $templateId, string $action): ?int
    {
        return Permission::where('name', "helpdesk.{$templateId}.{$action}")->value('id');
    }

    /** @return array<int> */
    private function getTemplatePermissionIds(): array
    {
        return Permission::where('name', 'like', 'helpdesk.%.%')
            ->pluck('id')
            ->toArray();
    }
}
