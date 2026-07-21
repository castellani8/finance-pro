<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Services\AssetMetricsRefresher;

/**
 * Mantém as métricas materializadas do ativo em dia: qualquer lançamento
 * criado/alterado/excluído recalcula posição, investido e valor atual.
 */
class TransactionObserver
{
    /** Importações em massa desligam o observer e recalculam tudo no final. */
    public static bool $enabled = true;

    public function saved(Transaction $transaction): void
    {
        $this->refresh($transaction);
    }

    public function deleted(Transaction $transaction): void
    {
        $this->refresh($transaction);
    }

    private function refresh(Transaction $transaction): void
    {
        if (! self::$enabled || $transaction->asset_id === null) {
            return;
        }

        $asset = $transaction->asset;

        if ($asset !== null) {
            app(AssetMetricsRefresher::class)->refreshAsset($asset);
        }
    }
}
