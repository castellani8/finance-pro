<?php

namespace App\Filament\Resources\Companies;

use Filament\Forms\Components\TextInput;

/**
 * Campos do formulário de empresa, compartilhados entre o CompanyResource e o
 * "criar empresa" inline do formulário de ativos.
 */
class CompanyForm
{
    /** @return array<int, TextInput> */
    public static function components(): array
    {
        return [
            TextInput::make('name')
                ->label('Nome / Razão social')
                ->required()
                ->maxLength(255),
            TextInput::make('document')
                ->label('CNPJ / CPF')
                ->placeholder('Somente números ou formatado')
                ->maxLength(20),
            TextInput::make('email')
                ->label('E-mail')
                ->email()
                ->maxLength(255),
            TextInput::make('phone')
                ->label('Telefone')
                ->tel()
                ->maxLength(20),
            TextInput::make('address')
                ->label('Endereço')
                ->maxLength(255),
            TextInput::make('city')
                ->label('Cidade')
                ->maxLength(100),
            TextInput::make('state')
                ->label('UF')
                ->maxLength(2),
            TextInput::make('zip')
                ->label('CEP')
                ->maxLength(10),
        ];
    }
}
