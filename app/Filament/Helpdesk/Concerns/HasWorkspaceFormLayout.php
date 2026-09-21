<?php

namespace App\Filament\Helpdesk\Concerns;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Alignment;

/**
 * Lays a create or edit page out like the workspace pages: an identity header followed by
 * a single form card whose footer holds the form actions.
 */
trait HasWorkspaceFormLayout
{
    /**
     * @return array{icon: string, eyebrow: string, title: string, description: string, badge?: array{label: string, tone: string}|null, backUrl?: string|null, backLabel?: string|null}
     */
    abstract protected function workspaceHero(): array;

    public function getFormContentComponent(): Component
    {
        return Form::make([
            View::make('filament.schemas.components.workspace-hero')
                ->viewData(fn (): array => $this->workspaceHero()),
            Section::make()
                ->schema([EmbeddedSchema::make('form')])
                ->footer([$this->getFormActionsContentComponent()]),
        ])
            ->id('form')
            ->livewireSubmitHandler($this->getSubmitFormLivewireMethodName());
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'fi-workspace-form'];
    }

    public function getFormActionsAlignment(): string|Alignment
    {
        return Alignment::End;
    }
}
