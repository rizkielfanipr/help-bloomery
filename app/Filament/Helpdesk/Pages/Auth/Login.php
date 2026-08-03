<?php

namespace App\Filament\Helpdesk\Pages\Auth;

use App\Models\User;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class Login extends BaseLogin
{
    protected static string $layout = 'filament.helpdesk.layouts.bare';

    protected string $view = 'filament.helpdesk.pages.auth.login';

    public function getTitle(): string|Htmlable
    {
        return new HtmlString('');
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Username')
            ->placeholder('Masukkan username Anda')
            ->prefixIcon('heroicon-o-user')
            ->autocomplete()
            ->autofocus()
            ->required();
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Kata Sandi')
            ->placeholder('Masukkan kata sandi Anda')
            ->prefixIcon('heroicon-o-lock-closed')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->required();
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
}
