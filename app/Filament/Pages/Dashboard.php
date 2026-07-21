<?php

namespace App\Filament\Pages;

use App\Models\Company;
use App\Support\CompanyFilter;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Dashboard com filtro global por empresa: todos os gráficos (evolução,
 * proventos, alocação e fluxo de caixa) passam a considerar apenas os
 * lançamentos e ativos da empresa escolhida.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(1)
                ->schema([
                    Select::make('company_id')
                        ->label('Filtrar por empresa')
                        // "+" preserva as chaves numéricas dos ids (spread as reindexaria).
                        ->options(fn (): array => [CompanyFilter::NONE => '— Sem empresa (pessoal)']
                            + Company::query()
                                ->where('tenant_id', Filament::getTenant()->getKey())
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                        ->placeholder('Todas as empresas')
                        ->native(false)
                        ->live(),
                ]),
        ]);
    }
}
