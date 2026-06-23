<?php

namespace App\Filament\Casual\Pages\Auth;

use App\Filament\Casual\Pages\PositionsPage;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class Register extends BaseRegister
{
    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.auth.register';

    public function getTitle(): string|Htmlable
    {
        return new HtmlString('');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getPhoneFormComponent(),
                $this->getBankNameFormComponent(),
                $this->getBankAccountNumberFormComponent(),
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

    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label('Nomor HP')
            ->tel()
            ->required()
            ->maxLength(20)
            ->unique(User::class, 'phone')
            ->placeholder('08xxxxxxxxxx');
    }

    protected function getBankNameFormComponent(): Component
    {
        return TextInput::make('bank_name')
            ->label('Nama Bank')
            ->required()
            ->maxLength(100)
            ->placeholder('BCA, BRI, Mandiri, ...');
    }

    protected function getBankAccountNumberFormComponent(): Component
    {
        return TextInput::make('bank_account_number')
            ->label('Nomor Rekening')
            ->required()
            ->maxLength(50)
            ->placeholder('Nomor rekening Anda');
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
        $data['is_active'] = true;
        $data['email'] = $data['phone'].'@casual.app';
        $data['username'] = $this->generateUsername($data['phone']);

        return $data;
    }

    private function generateUsername(string $phone): string
    {
        $base = 'casual_'.preg_replace('/\D/', '', $phone);
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

        $user->assignRole('casual_staff');

        return $user;
    }

    public function register(): ?RegistrationResponse
    {
        $response = parent::register();

        if ($response !== null) {
            redirect(PositionsPage::getUrl());

            return null;
        }

        return null;
    }
}
