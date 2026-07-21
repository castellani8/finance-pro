<?php

namespace App\Filament\Resources\Contas\Pages;

use App\Filament\Resources\Contas\ContaResource;
use App\Models\PortfolioSnapshot;
use App\Support\PortfolioCache;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListContas extends ListRecords
{
    protected static string $resource = ContaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nova conta')
                ->mutateDataUsing(function (array $data): array {
                    $data['tenant_id'] = Filament::getTenant()->getKey();

                    return $data;
                })
                ->after(fn () => self::invalidate()),
        ];
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->recordActions([
                EditAction::make()->after(fn () => self::invalidate()),
                DeleteAction::make()
                    ->modalDescription('Os lançamentos vinculados serão mantidos, apenas desvinculados da conta.')
                    ->after(fn () => self::invalidate()),
            ]);
    }

    /** Saldo entra no patrimônio: mudanças em contas invalidam séries e cache. */
    private static function invalidate(): void
    {
        $tenantId = Filament::getTenant()->getKey();

        PortfolioSnapshot::where('tenant_id', $tenantId)->delete();
        PortfolioCache::bump($tenantId);
    }
}
