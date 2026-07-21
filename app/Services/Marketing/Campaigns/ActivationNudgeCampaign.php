<?php

namespace App\Services\Marketing\Campaigns;

use App\Models\Transaction;
use App\Models\User;
use App\Services\Marketing\Campaign;

/**
 * Dia 3 — só para quem ainda não registrou nenhuma movimentação: remove a
 * fricção do primeiro passo apontando para a importação da B3.
 */
class ActivationNudgeCampaign extends Campaign
{
    public function key(): string
    {
        return 'activation_d3';
    }

    public function dueDay(): int
    {
        return 3;
    }

    public function appliesTo(User $user): bool
    {
        $tenantIds = $user->tenants->pluck('id');

        return $tenantIds->isNotEmpty()
            && ! Transaction::query()->whereIn('tenant_id', $tenantIds)->exists();
    }

    public function subject(User $user): string
    {
        return $this->firstName($user).', sua carteira ainda está vazia — 2 minutos resolvem';
    }

    public function preheader(User $user): string
    {
        return 'Importe a planilha da B3 e veja sua carteira consolidada na hora.';
    }

    public function headline(User $user): string
    {
        return 'O primeiro passo leva menos tempo que um café.';
    }

    public function paragraphs(User $user): array
    {
        return [
            'Notamos que sua carteira ainda não tem movimentações. Sem elas, o painel não consegue mostrar sua rentabilidade, seus proventos nem comparar você com o CDI.',
            'Baixe o relatório de movimentação na área do investidor da B3 (Extratos → Movimentação) e importe o arquivo em Ativos → Importar planilha B3. O resto — cotações, câmbio, gráficos — a Milia Invest faz sozinha.',
        ];
    }

    public function bullets(User $user): array
    {
        return [
            'Posições e preço médio calculados automaticamente',
            'Proventos organizados por mês e por ativo',
            'Imóveis, veículos e renda fixa entram manualmente em poucos cliques',
        ];
    }

    public function ctaLabel(): string
    {
        return 'Importar minha planilha B3';
    }
}
