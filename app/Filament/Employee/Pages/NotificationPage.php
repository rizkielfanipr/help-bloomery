<?php

namespace App\Filament\Employee\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

class NotificationPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.employee.layouts.bare';

    protected string $view = 'filament.employee.pages.notification-page';

    public function getTitle(): string|Htmlable
    {
        return 'Notifikasi';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function mount(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    #[Computed]
    public function notifications(): Collection
    {
        return auth()->user()->notifications()->latest()->limit(50)->get();
    }
}
