<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Tenant;

/**
 * Materializa posição, investido e valor atual (BRL) nas colunas do ativo.
 * Chamado pelo observer de transações (lançamento a lançamento), pelo job de
 * snapshot diário (preços/câmbio mudam) e pelo botão "Atualizar valores".
 */
class AssetMetricsRefresher
{
    public function refreshAsset(Asset $asset): void
    {
        $fresh = $asset->fresh()?->load('transactions');

        if ($fresh === null) {
            return;
        }

        // Query builder (sem eventos): não dispara observer/activitylog.
        Asset::whereKey($fresh->getKey())->update([
            'position_quantity' => round($fresh->positionQuantity(), 6),
            'invested_value' => round($fresh->purchaseValue(), 2),
            'current_value' => round($fresh->currentValue(), 2),
            'metrics_refreshed_at' => now(),
        ]);
    }

    /** @return int Quantidade de ativos atualizados. */
    public function refreshTenant(Tenant $tenant): int
    {
        $assets = Asset::query()
            ->where('tenant_id', $tenant->getKey())
            ->with('transactions')
            ->get();

        foreach ($assets as $asset) {
            Asset::whereKey($asset->getKey())->update([
                'position_quantity' => round($asset->positionQuantity(), 6),
                'invested_value' => round($asset->purchaseValue(), 2),
                'current_value' => round($asset->currentValue(), 2),
                'metrics_refreshed_at' => now(),
            ]);
        }

        return $assets->count();
    }
}
