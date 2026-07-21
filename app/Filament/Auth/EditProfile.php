<?php

namespace App\Filament\Auth;

use App\Filament\Auth\Concerns\HasPhoneFormComponents;
use App\Support\PhoneCountry;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use SensitiveParameter;

/**
 * Tela "Meu perfil": dados pessoais (nome e celular, mantendo E.164 em
 * users.phone) e troca de senha. E-mail é exibido mas não editável por
 * enquanto — mudá-lo exigiria reverificação.
 */
class EditProfile extends BaseEditProfile
{
    use HasPhoneFormComponents;

    public function getTitle(): string
    {
        return 'Meu perfil';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->inlineLabel(false)
            ->components([
                Section::make('Dados pessoais')
                    ->description('Como você aparece na Milia Invest e onde te avisamos sobre a sua carteira.')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->aside()
                    ->schema([
                        $this->getNameFormComponent()->label('Nome'),
                        TextInput::make('email')
                            ->label('E-mail')
                            ->disabled()
                            ->dehydrated(false)
                            ->belowContent('A alteração de e-mail ainda não está disponível.'),
                        $this->getPhoneFormComponent(),
                    ]),
                Section::make('Alterar senha')
                    ->description('Deixe em branco para manter a senha atual. Ao definir uma nova senha, confirme também a senha atual.')
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->aside()
                    ->schema([
                        $this->getPasswordFormComponent()->label('Nova senha'),
                        $this->getPasswordConfirmationFormComponent()->label('Confirmar nova senha'),
                        $this->getCurrentPasswordFormComponent()->label('Senha atual'),
                    ]),
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
