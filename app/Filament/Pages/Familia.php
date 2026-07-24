<?php

namespace App\Filament\Pages;

use App\Mail\TenantInvitationMail;
use App\Models\TenantInvitation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class Familia extends Page
{
    protected string $view = 'filament.pages.familia';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Família';

    protected static ?string $title = 'Família e sucessão';

    protected static ?int $navigationSort = 60;

    /** @return Collection<int, \App\Models\User> */
    public function getMembers(): Collection
    {
        return Filament::getTenant()->users()->orderBy('name')->get();
    }

    /** @return Collection<int, TenantInvitation> */
    public function getPendingInvitations(): Collection
    {
        return TenantInvitation::query()
            ->where('tenant_id', Filament::getTenant()->getKey())
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->get();
    }

    public function revokeInvitation(int $invitationId): void
    {
        TenantInvitation::query()
            ->where('tenant_id', Filament::getTenant()->getKey())
            ->whereKey($invitationId)
            ->delete();

        Notification::make()->title('Convite revogado')->success()->send();
    }

    public function removeMember(int $userId): void
    {
        $tenant = Filament::getTenant();

        // Ninguém se remove sozinho por aqui — evita carteira órfã sem querer.
        if ($userId === auth()->id()) {
            Notification::make()
                ->title('Você não pode remover a si mesmo')
                ->body('Para sair da carteira, peça a outro membro que remova o seu acesso.')
                ->warning()
                ->send();

            return;
        }

        $tenant->users()->detach($userId);

        Notification::make()->title('Acesso removido')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('convidar')
                ->label('Convidar para a carteira')
                ->icon('heroicon-o-user-plus')
                ->modalHeading('Convidar para a carteira')
                ->modalDescription('A pessoa passa a ver e registrar tudo nesta carteira — patrimônio, contas e relatórios. Ideal para cônjuge ou família próxima.')
                ->schema([
                    TextInput::make('email')
                        ->label('E-mail de quem você quer convidar')
                        ->email()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $tenant = Filament::getTenant();
                    $email = mb_strtolower(trim($data['email']));

                    if ($tenant->users()->where('email', $email)->exists()) {
                        Notification::make()->title('Essa pessoa já participa da carteira')->warning()->send();

                        return;
                    }

                    $pending = TenantInvitation::query()
                        ->where('tenant_id', $tenant->getKey())
                        ->where('email', $email)
                        ->whereNull('accepted_at')
                        ->where('expires_at', '>', now())
                        ->exists();

                    if ($pending) {
                        Notification::make()->title('Já existe um convite pendente para este e-mail')->warning()->send();

                        return;
                    }

                    $invitation = TenantInvitation::createFor($tenant, $email, auth()->user());

                    Mail::to($email)->queue(new TenantInvitationMail($invitation));

                    Notification::make()
                        ->title('Convite enviado')
                        ->body("Enviamos um e-mail para {$email} com validade de ".TenantInvitation::VALID_DAYS.' dias.')
                        ->success()
                        ->send();
                }),
            Action::make('carta')
                ->label('Carta de patrimônio')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->tooltip('Documento imprimível com onde está cada ativo e conta — para a família saber a quem recorrer se algo acontecer com você.')
                ->url(fn (): string => route('carta.patrimonio', ['tenant' => Filament::getTenant()->uuid]), shouldOpenInNewTab: true),
        ];
    }
}
