<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\Transaction;
use App\Services\AccountDeletion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class PrivacidadeEDados extends Page
{
    protected string $view = 'filament.pages.privacidade-e-dados';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Privacidade e dados';

    protected static ?string $title = 'Privacidade e dados';

    protected static ?int $navigationSort = 90;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportData')
                ->label('Exportar meus dados (JSON)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $payload = app(AccountDeletion::class)->export(Auth::user());

                    return response()->streamDownload(
                        fn () => print (json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
                        'finance-pro-meus-dados-'.now()->format('Y-m-d').'.json',
                        ['Content-Type' => 'application/json'],
                    );
                }),

            Action::make('deleteAccount')
                ->label('Excluir minha conta')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Excluir conta definitivamente')
                ->modalDescription('Todos os seus dados financeiros (ativos, movimentações e histórico) serão apagados de forma irreversível. Esta ação não pode ser desfeita.')
                ->modalSubmitActionLabel('Excluir tudo')
                ->schema([
                    TextInput::make('password')
                        ->label('Confirme sua senha')
                        ->password()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $user = Auth::user();

                    if (! Hash::check($data['password'], $user->password)) {
                        Notification::make()
                            ->title('Senha incorreta')
                            ->danger()
                            ->send();

                        return;
                    }

                    app(AccountDeletion::class)->delete($user);
                    Auth::logout();
                    session()->invalidate();
                    session()->regenerateToken();

                    return redirect('/');
                }),
        ];
    }

    /** @return array<string, int> */
    public function getDataSummary(): array
    {
        $tenant = Filament::getTenant();

        return [
            'ativos' => Asset::where('tenant_id', $tenant->getKey())->count(),
            'movimentacoes' => Transaction::where('tenant_id', $tenant->getKey())->count(),
        ];
    }
}
