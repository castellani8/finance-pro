<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Tenant;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant as BaseRegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class RegisterTenant extends BaseRegisterTenant
{
    public static function getLabel(): string
    {
        return 'Criar sua carteira';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nome da carteira')
                ->placeholder('Ex: Minha carteira, Família Silva')
                ->helperText('A carteira agrupa suas contas, ativos e lançamentos. Você pode criar outras depois — por exemplo, uma para a família e outra para a empresa.')
                ->autofocus()
                ->required()
                ->maxLength(100),
        ]);
    }

    protected function handleRegistration(array $data): Tenant
    {
        $tenant = new Tenant;
        $tenant->forceFill([
            'name' => $data['name'],
            'uuid' => (string) Str::uuid(),
        ])->save();

        $tenant->users()->attach(auth()->user());

        return $tenant;
    }
}
