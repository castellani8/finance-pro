<?php

namespace App\Filament\Resources\Recorrencias\Pages;

use App\Filament\Resources\Recorrencias\RecorrenciaResource;
use App\Support\PortfolioCache;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListRecorrencias extends ListRecords
{
    protected static string $resource = RecorrenciaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RecorrenciaResource::generateNowAction(),
            CreateAction::make()
                ->label('Nova recorrência')
                ->mutateDataUsing(function (array $data): array {
                    $data['tenant_id'] = Filament::getTenant()->getKey();

                    return $data;
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->recordActions([
                EditAction::make()
                    ->after(fn () => PortfolioCache::bump(Filament::getTenant()->getKey())),
                DeleteAction::make()
                    ->modalDescription('Os lançamentos já gerados por esta recorrência serão mantidos.')
                    ->after(fn () => PortfolioCache::bump(Filament::getTenant()->getKey())),
            ]);
    }
}
