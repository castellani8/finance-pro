<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\PortfolioSnapshot;
use App\Models\Transaction;
use App\Support\PortfolioCache;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateAsset extends CreateRecord
{
    protected static string $resource = AssetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = Filament::getTenant()->getKey();

        return $data;
    }

    /**
     * Ativos físicos nascem com o lançamento de aquisição (senão a posição
     * seria zero e o bem nem apareceria na listagem).
     */
    protected function afterCreate(): void
    {
        $asset = $this->getRecord();
        $tenantId = Filament::getTenant()->getKey();

        if ($asset->isPhysical()) {
            $value = (float) ($this->data['acquisition_value'] ?? 0);
            $quantity = max(0.000001, (float) ($this->data['acquisition_quantity'] ?? 1));
            $date = $this->data['acquisition_date'] ?? now()->toDateString();

            Transaction::create([
                'tenant_id' => $tenantId,
                'asset_id' => $asset->getKey(),
                'account_id' => $this->data['acquisition_account_id'] ?? null,
                'type' => 'BUY',
                'transaction_date' => $date,
                'quantity' => $quantity,
                'unit_price' => $quantity > 0 ? $value / $quantity : null,
                'total_amount' => $value,
                'direction' => 'Credito',
                'movement' => 'Aquisição',
                'source' => 'manual',
            ]);
        }

        PortfolioSnapshot::where('tenant_id', $tenantId)->delete();
        PortfolioCache::bump($tenantId);
    }
}
