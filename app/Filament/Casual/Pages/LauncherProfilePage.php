<?php

namespace App\Filament\Casual\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

class LauncherProfilePage extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.casual.pages.launcher-profile-page';

    protected static string $layout = 'filament.casual.layouts.bare';

    public $photo = null;

    public function getTitle(): string|Htmlable
    {
        return 'Profil';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function savePhoto(): void
    {
        $this->validate(['photo' => ['required', 'image', 'max:5120']]);

        $user = auth()->user();

        if ($user->avatar) {
            Storage::disk('b2')->delete($user->avatar);
        }

        $path = $this->photo->store('avatars', 'b2');

        $user->update(['avatar' => $path]);

        $this->photo = null;

        Notification::make()
            ->title('Foto profil berhasil diperbarui')
            ->success()
            ->send();

        $this->redirect(static::getUrl());
    }

    public function comingSoon(): void
    {
        Notification::make()
            ->title('Segera Hadir')
            ->body('Fitur ini sedang dalam pengembangan.')
            ->info()
            ->send();
    }

    public function logout(): void
    {
        filament()->auth()->logout();

        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(filament()->getLoginUrl());
    }
}
