<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Assets\Widgets\AssetStatsOverview;
use App\Models\PortfolioSnapshot;
use App\Support\PortfolioCache;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditAsset extends EditRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** Análise financeira do ativo (compra, custo, valor, renda, resultado). */
    protected function getHeaderWidgets(): array
    {
        return [
            AssetStatsOverview::make(['record' => $this->getRecord()]),
        ];
    }

    /**
     * Editar taxa de depreciação/indexador muda o valor calculado do ativo:
     * invalida snapshots e cache para o recálculo aparecer na hora.
     */
    protected function afterSave(): void
    {
        $tenantId = Filament::getTenant()->getKey();

        PortfolioSnapshot::where('tenant_id', $tenantId)->delete();
        PortfolioCache::bump($tenantId);
    }
}
