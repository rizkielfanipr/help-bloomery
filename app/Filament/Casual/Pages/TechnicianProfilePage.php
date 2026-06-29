<?php

namespace App\Filament\Casual\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

class TechnicianProfilePage extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.technician-profile-page';

    public $photo = null;

    public function getTitle(): string|Htmlable
    {
        return 'Profil Teknisi';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function savePhoto(): void
    {
        $this->validate(['photo' => ['required', 'image', 'max:5120']]);

        $user = auth()->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $this->photo->store('avatars', 'public');

        $user->update(['avatar' => $path]);

        $this->photo = null;

        Notification::make()
            ->title('Foto profil berhasil diperbarui')
            ->success()
            ->send();

        $this->redirect(static::getUrl());
    }

    public function logout(): void
    {
        filament()->auth()->logout();

        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(filament()->getLoginUrl());
    }
}
