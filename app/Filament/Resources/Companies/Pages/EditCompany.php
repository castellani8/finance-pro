<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Widgets\CashFlowChart;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** Fluxo de caixa filtrado por esta empresa (lançamentos diretos + dos ativos dela). */
    protected function getHeaderWidgets(): array
    {
        return [
            CashFlowChart::make(['companyId' => $this->getRecord()->getKey()]),
        ];
    }
}
