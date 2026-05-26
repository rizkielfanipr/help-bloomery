<?php

namespace App\Filament\Casual\Pages;

use App\Models\CasualPosition;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SelectPosition extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.casual.pages.select-position';

    public function mount(): void
    {
        // Already has a position, go to clock page
        if (auth()->user()->casual_position_id !== null) {
            $this->redirect(ClockPage::getUrl());
        }
    }

    public function selectPosition(int $positionId): void
    {
        $position = CasualPosition::where('id', $positionId)
            ->where('is_active', true)
            ->firstOrFail();

        auth()->user()->update(['casual_position_id' => $position->id]);

        Notification::make()
            ->title('Posisi dipilih: '.$position->name)
            ->success()
            ->send();

        $this->redirect(ClockPage::getUrl());
    }

    public function getPositions()
    {
        return CasualPosition::where('is_active', true)->orderBy('name')->get();
    }
}
