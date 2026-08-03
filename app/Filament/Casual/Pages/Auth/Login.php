<?php

namespace App\Filament\Casual\Pages\Auth;

use App\Filament\Casual\Pages\LauncherPage;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class Login extends BaseLogin
{
    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.auth.login';

    public function getTitle(): string|Htmlable
    {
        return new HtmlString('');
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Username')
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->placeholder('Masukkan username Anda');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        $login = (string) $data['email'];
        $usesUsername = User::where('username', $login)->exists();

        return [
            $usesUsername ? 'username' : 'email' => $login,
            'password' => $data['password'],
        ];
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Kata Sandi')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->required();
    }

    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')
            ->label('Ingat Saya')
            ->visible(false);
    }

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            $user = Filament::auth()->user();
            redirect($this->resolveHomeUrl($user));

            return;
        }

        $this->form->fill();
    }

    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        if ($response !== null) {
            $user = Filament::auth()->user();
            redirect($this->resolveHomeUrl($user));

            return null;
        }

        return null;
    }

    private function resolveHomeUrl(mixed $user): string
    {
        return LauncherPage::getUrl();
    }
}
