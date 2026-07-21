<?php

namespace App\Console\Commands;

use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Contas\ContaResource;
use App\Filament\Resources\Recorrencias\RecorrenciaResource;
use App\Models\Account;
use App\Models\Asset;
use App\Models\RecurringTransaction;
use App\Models\Tenant;
use App\Services\AlertDispatcher;
use App\Services\CurrencyConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NotifyAlerts extends Command
{
    protected $signature = 'portfolio:notify-alerts
                            {--stale-days=7 : Dias sem cotação para alertar ativos de bolsa}';

    protected $description = 'Alertas diários: renda fixa vencendo, recorrências terminando, ativos sem cotação e contas negativas';

    /** Antecedências (dias) para vencimentos e fins de contrato. */
    private const THRESHOLDS = [30, 7, 0];

    public function handle(AlertDispatcher $alerts): int
    {
        $sent = 0;

        foreach (Tenant::with('users')->get() as $tenant) {
            $sent += $this->maturities($tenant, $alerts);
            $sent += $this->endingRecurrences($tenant, $alerts);
            $sent += $this->staleQuotes($tenant, $alerts);
            $sent += $this->negativeBalances($tenant, $alerts);
        }

        $this->info("{$sent} alerta(s) enviado(s).");

        return self::SUCCESS;
    }

    /** Renda fixa com posição vencendo em 30/7/0 dias. */
    private function maturities(Tenant $tenant, AlertDispatcher $alerts): int
    {
        $today = now()->startOfDay();
        $sent = 0;

        $assets = Asset::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('type', 'FIXED_INCOME')
            ->wherePositionPositive()
            ->get()
            ->filter(fn (Asset $asset): bool => ! empty($asset->metadata['due_date'] ?? null));

        foreach ($assets as $asset) {
            $daysLeft = (int) $today->diffInDays(Carbon::parse($asset->metadata['due_date'])->startOfDay(), false);

            if (! in_array($daysLeft, self::THRESHOLDS, true)) {
                continue;
            }

            $title = $daysLeft === 0 ? "Vence hoje: {$asset->name}" : "Vence em {$daysLeft} dias: {$asset->name}";

            foreach ($tenant->users as $user) {
                $sent += (int) $alerts->send(
                    $user,
                    $title,
                    'Quando o dinheiro cair, use "Registrar resgate" no ativo para informar o valor líquido e creditar numa conta.',
                    AssetResource::getUrl('edit', ['record' => $asset, 'tenant' => $tenant]),
                );
            }
        }

        return $sent;
    }

    /** Contratos recorrentes ativos chegando ao fim (ends_on em 30/7/0 dias). */
    private function endingRecurrences(Tenant $tenant, AlertDispatcher $alerts): int
    {
        $today = now()->startOfDay();
        $sent = 0;

        $contracts = RecurringTransaction::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('active', true)
            ->whereNotNull('ends_on')
            ->get();

        foreach ($contracts as $contract) {
            $daysLeft = (int) $today->diffInDays($contract->ends_on->copy()->startOfDay(), false);

            if (! in_array($daysLeft, self::THRESHOLDS, true)) {
                continue;
            }

            $title = $daysLeft === 0
                ? "Contrato recorrente termina hoje: {$contract->description}"
                : "Contrato recorrente termina em {$daysLeft} dias: {$contract->description}";

            foreach ($tenant->users as $user) {
                $sent += (int) $alerts->send(
                    $user,
                    $title,
                    'Renove o contrato (novo fim) ou deixe encerrar — os lançamentos param sozinhos.',
                    RecorrenciaResource::getUrl(parameters: ['tenant' => $tenant]),
                );
            }
        }

        return $sent;
    }

    /** Ativos de bolsa com posição cuja cotação está velha (ou nunca veio). */
    private function staleQuotes(Tenant $tenant, AlertDispatcher $alerts): int
    {
        $staleDays = max(1, (int) $this->option('stale-days'));
        $cutoff = now()->subDays($staleDays)->toDateString();

        $tickers = Asset::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('type', ['STOCK', 'FII'])
            ->wherePositionPositive()
            ->whereNotNull('ticker_or_code')
            ->pluck('ticker_or_code')
            ->unique();

        if ($tickers->isEmpty()) {
            return 0;
        }

        $latestByTicker = DB::table('asset_price_history')
            ->whereIn('ticker', $tickers)
            ->groupBy('ticker')
            ->pluck(DB::raw('max(date) as latest'), 'ticker');

        $stale = $tickers
            ->filter(fn (string $ticker): bool => substr((string) ($latestByTicker[$ticker] ?? ''), 0, 10) < $cutoff)
            ->values();

        if ($stale->isEmpty()) {
            return 0;
        }

        $title = $stale->count().' ativo(s) sem cotação há mais de '.$staleDays.' dias';
        $body = 'Sem cotação recente, o valor exibido pode estar defasado: '
            .$stale->take(10)->implode(', ')
            .($stale->count() > 10 ? '…' : '')
            .'. Verifique a sincronização de preços.';
        $sent = 0;

        foreach ($tenant->users as $user) {
            $sent += (int) $alerts->send(
                $user,
                $title,
                $body,
                AssetResource::getUrl(parameters: ['tenant' => $tenant]),
            );
        }

        return $sent;
    }

    /** Contas com saldo negativo. */
    private function negativeBalances(Tenant $tenant, AlertDispatcher $alerts): int
    {
        $sent = 0;

        $negative = Account::query()
            ->where('tenant_id', $tenant->getKey())
            ->with('transactions')
            ->get()
            ->filter(fn ($account): bool => $account->balance() < -0.005);

        foreach ($negative as $account) {
            $title = "Conta negativa: {$account->name}";
            $body = 'Saldo atual: -'.CurrencyConverter::symbol($account->currency).' '
                .number_format(abs($account->balance()), 2, ',', '.')
                .'. Confira os lançamentos ou ajuste o saldo inicial.';

            foreach ($tenant->users as $user) {
                $sent += (int) $alerts->send(
                    $user,
                    $title,
                    $body,
                    ContaResource::getUrl(parameters: ['tenant' => $tenant]),
                );
            }
        }

        return $sent;
    }
}
