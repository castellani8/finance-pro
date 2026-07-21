<?php

namespace App\Filament\Auth;

use App\Filament\Auth\Concerns\HasPhoneFormComponents;
use App\Support\PhoneCountry;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Schema;
use SensitiveParameter;

/**
 * Perfil com edição do celular (DDI + número), mantendo E.164 em users.phone.
 */
class EditProfile extends BaseEditProfile
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
                $this->getCurrentPasswordFormComponent(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        [$data['phone_country'], $data['phone_number']] = PhoneCountry::split($data['phone'] ?? null);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(#[SensitiveParameter] array $data): array
    {
        $data['phone'] = PhoneCountry::e164($data['phone_country'], $data['phone_number']);

        unset($data['phone_country'], $data['phone_number']);

        return $data;
    }
}
