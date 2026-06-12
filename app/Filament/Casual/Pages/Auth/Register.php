<?php

namespace App\Filament\Casual\Pages\Auth;

use App\Models\CasualRegistrationToken;
use App\Models\User;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class Register extends BaseRegister
{
    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.auth.register';

    public function getTitle(): string|Htmlable
    {
        return new HtmlString('');
    }

    /** @var CasualRegistrationToken|null Stored between mutate and afterRegister hooks */
    protected ?CasualRegistrationToken $tokenRecord = null;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('token')
                    ->label('Token Registrasi')
                    ->required()
                    ->extraAttributes(['autocomplete' => 'off']),

                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    protected function getNameFormComponent(): Component
    {
        return TextInput::make('name')
            ->label('Nama Lengkap')
            ->required()
            ->maxLength(255)
            ->autofocus();
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email')
            ->email()
            ->required()
            ->maxLength(255)
            ->autocomplete('email');
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Kata Sandi')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->autocomplete('new-password');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('passwordConfirmation')
            ->label('Konfirmasi Kata Sandi')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->autocomplete('new-password')
            ->same('password');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeRegister(array $data): array
    {
        $token = trim(strtoupper($data['token'] ?? ''));
        unset($data['token']);

        $registrationToken = CasualRegistrationToken::where('token', $token)->first();

        if (! $registrationToken || ! $registrationToken->isValid()) {
            throw ValidationException::withMessages([
                'data.token' => 'Token tidak valid, sudah digunakan, atau sudah kadaluarsa.',
            ]);
        }

        $this->tokenRecord = $registrationToken;

        $data['is_active'] = true;
        $data['username'] = $this->generateUsername($data['email']);

        return $data;
    }

    private function generateUsername(string $email): string
    {
        $base = strtolower(str_replace([' ', '.', '+'], '_', explode('@', $email)[0]));
        $username = $base;
        $suffix = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base.'_'.$suffix;
            $suffix++;
        }

        return $username;
    }

    protected function handleRegistration(array $data): Model
    {
        /** @var Model $user */
        $user = parent::handleRegistration($data);

        // Assign role and mark token inside same transaction
        $user->assignRole('casual_staff');
        $this->tokenRecord?->markAsUsed($user->id);

        return $user;
    }
}
