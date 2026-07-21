<?php

namespace App\Filament\Pages;

use App\Models\Subscription;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Central da assinatura: status, dias restantes do teste e o plano único.
 * Quando a cobrança via Asaas entrar, o botão de assinar passa a criar a
 * assinatura no gateway — a página já está pronta para isso.
 */
class Assinatura extends Page
{
    protected string $view = 'filament.pages.assinatura';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Assinatura';

    protected static ?string $title = 'Assinatura';

    protected static ?int $navigationSort = 95;

    public function getSubscription(): ?Subscription
    {
        return auth()->user()?->subscription;
    }

    public function getPlanPrice(): string
    {
        return (string) config('landing.plan.price');
    }

    /**
     * @return list<string>
     */
    public function getPlanFeatures(): array
    {
        return [
            'Ativos ilimitados em todas as classes',
            'Cotações da B3 e câmbio atualizados diariamente',
            'Renda passiva, fluxo de caixa e recorrências',
            'Relatório anual pronto para o Imposto de Renda',
            'Comparação da carteira com CDI e IBOV',
            'Alertas automáticos no painel e por e-mail',
            'Cancele quando quiser, sem multa',
        ];
    }
}
