<?php

namespace App\Filament\Auth;

use App\Filament\Auth\Concerns\HasPhoneFormComponents;
use App\Support\PhoneCountry;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/**
 * Cadastro com celular obrigatório (DDI + número), persistido em E.164.
 */
class Register extends BaseRegister
{
    use HasPhoneFormComponents;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPhoneFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        $data['phone'] = PhoneCountry::e164($data['phone_country'], $data['phone_number']);

        unset($data['phone_country'], $data['phone_number']);

        return parent::handleRegistration($data);
    }
}
