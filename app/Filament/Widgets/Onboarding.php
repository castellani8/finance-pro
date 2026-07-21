<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\RendaPassiva;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Contas\ContaResource;
use App\Filament\Resources\Lancamentos\LancamentoResource;
use App\Filament\Resources\Recorrencias\RecorrenciaResource;
use App\Models\Account;
use App\Models\Asset;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Guia de primeiros passos do dashboard: um checklist cujo estado é derivado
 * dos dados reais do tenant (contas, ativos, lançamentos, recorrências e meta
 * de renda passiva), sem flags por etapa. Some quando o usuário o dispensa —
 * persistido em users.onboarding_dismissed_at.
 */
class Onboarding extends Widget
{
    protected string $view = 'filament.widgets.onboarding';

    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    /** Esconde o widget na mesma requisição em que o usuário o dispensa. */
    public bool $dismissed = false;

    public static function canView(): bool
    {
        return Filament::getTenant() !== null
            && auth()->user()?->onboarding_dismissed_at === null;
    }

    public function dismiss(): void
    {
        auth()->user()->forceFill(['onboarding_dismissed_at' => now()])->save();

        $this->dismissed = true;
    }

    /**
     * @return array<int, array{title: string, description: string, done: bool, url: ?string, cta: ?string}>
     */
    protected function getSteps(): array
    {
        $tenant = Filament::getTenant();
        $tenantId = $tenant->getKey();

        return [
            [
                'title' => 'Crie sua carteira',
                'description' => 'A carteira "'.$tenant->name.'" está pronta para receber seus dados.',
                'done' => true,
                'url' => null,
                'cta' => null,
            ],
            [
                'title' => 'Cadastre suas contas',
                'description' => 'Banco, corretora ou dinheiro em caixa — é por elas que o dinheiro entra e sai.',
                'done' => Account::query()->where('tenant_id', $tenantId)->exists(),
                'url' => ContaResource::getUrl(),
                'cta' => 'Criar minha primeira conta',
            ],
            [
                'title' => 'Adicione seus ativos',
                'description' => 'Importe a planilha da B3 (Extratos → Movimentação) e veja posições e proventos na hora — ou cadastre imóveis, veículos e renda fixa manualmente.',
                'done' => Asset::query()->where('tenant_id', $tenantId)->exists(),
                'url' => AssetResource::getUrl(),
                'cta' => 'Importar planilha B3',
            ],
            [
                'title' => 'Registre movimentações',
                'description' => 'Compras, vendas, receitas e despesas alimentam o fluxo de caixa, a rentabilidade e o relatório de IR.',
                'done' => Transaction::query()->where('tenant_id', $tenantId)->exists(),
                'url' => LancamentoResource::getUrl(),
                'cta' => 'Fazer um lançamento',
            ],
            [
                'title' => 'Automatize recorrências',
                'description' => 'Aluguéis, assinaturas e contratos viram lançamentos automáticos no vencimento.',
                'done' => RecurringTransaction::query()->where('tenant_id', $tenantId)->exists(),
                'url' => RecorrenciaResource::getUrl(),
                'cta' => 'Criar uma recorrência',
            ],
            [
                'title' => 'Defina sua meta de renda passiva',
                'description' => 'Acompanhe quanto falta para os proventos pagarem suas contas todo mês.',
                'done' => $tenant->passive_income_goal !== null,
                'url' => RendaPassiva::getUrl(),
                'cta' => 'Definir minha meta',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $steps = $this->getSteps();
        $completed = count(array_filter($steps, fn (array $step): bool => $step['done']));
        $total = count($steps);

        return [
            'steps' => $steps,
            'completed' => $completed,
            'total' => $total,
            'percent' => (int) round($completed / $total * 100),
            'allDone' => $completed === $total,
        ];
    }
}
