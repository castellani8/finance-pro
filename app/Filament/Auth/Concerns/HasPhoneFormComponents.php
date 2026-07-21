<?php

namespace App\Filament\Auth\Concerns;

use App\Support\PhoneCountry;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Campos de celular (DDI + número) compartilhados entre o cadastro e o
 * perfil. O valor persiste em users.phone no formato E.164.
 */
trait HasPhoneFormComponents
{
    protected function getPhoneFormComponent(): Grid
    {
        return Grid::make(['default' => 5])
            ->schema([
                Select::make('phone_country')
                    ->label('País')
                    ->options(PhoneCountry::options())
                    ->default('55')
                    ->selectablePlaceholder(false)
                    ->searchable()
                    ->native(false)
                    ->required()
                    ->columnSpan(2),
                TextInput::make('phone_number')
                    ->label('Celular')
                    ->tel()
                    ->placeholder('(11) 98765-4321')
                    ->required()
                    ->maxLength(20)
                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        $digits = preg_replace('/\D/', '', (string) $value);
                        $country = (string) $get('phone_country');

                        if ($country === '55') {
                            // Brasil: DDD (2 dígitos) + celular de 9 dígitos começando em 9.
                            if (! preg_match('/^[1-9][0-9]9[0-9]{8}$/', $digits)) {
                                $fail('Informe DDD + celular, ex.: (11) 98765-4321.');
                            }

                            return;
                        }

                        $totalDigits = strlen($country.$digits);

                        if (strlen($digits) < 6 || $totalDigits > 15) {
                            $fail('Informe um número de celular válido.');
                        }
                    })
                    ->validationAttribute('celular')
                    ->columnSpan(3),
            ]);
    }
}
