<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\CheckboxList;
use Spatie\Permission\Models\Permission;

class PermissionsMatrix extends CheckboxList
{
    protected string $view = 'filament.forms.components.permissions-matrix';

    protected function setUp(): void
    {
        parent::setUp();

        $this->relationship('permissions', 'name');

        $this->options(
            Permission::orderBy('name')->pluck('name', 'id')->toArray()
        );
    }
}
