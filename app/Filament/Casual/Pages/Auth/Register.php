<?php

namespace App\Filament\Casual\Pages\Auth;

use App\Models\CasualRegistrationToken;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Register extends BaseRegister
{
    /** @var CasualRegistrationToken|null Stored between mutate and afterRegister hooks */
    protected ?CasualRegistrationToken $tokenRecord = null;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('token')
                    ->label('Token Registrasi')
                    ->required()
                    ->placeholder('Masukkan token dari HR Admin')
                    ->helperText('Token diberikan oleh HR Admin untuk proses pendaftaran')
                    ->extraAttributes(['autocomplete' => 'off']),

                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
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

        return $data;
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
