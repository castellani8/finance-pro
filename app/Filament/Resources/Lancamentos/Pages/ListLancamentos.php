<?php

namespace App\Filament\Resources\Lancamentos\Pages;

use App\Enums\FlowDirection;
use App\Filament\Resources\Lancamentos\LancamentoResource;
use App\Models\PortfolioSnapshot;
use App\Support\PortfolioCache;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListLancamentos extends ListRecords
{
    protected static string $resource = LancamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Novo lançamento')
                ->modalHeading('Novo lançamento avulso')
                ->mutateDataUsing(fn (array $data): array => self::prepareData($data))
                ->after(fn () => self::invalidate()),
        ];
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->recordActions([
                EditAction::make()
                    ->mutateDataUsing(fn (array $data): array => self::prepareData($data))
                    ->after(fn () => self::invalidate()),
                DeleteAction::make()
                    ->after(fn () => self::invalidate()),
            ]);
    }

    /** Lançamentos vinculados a conta mexem no saldo — que entra no patrimônio. */
    private static function invalidate(): void
    {
        $tenantId = Filament::getTenant()->getKey();

        PortfolioSnapshot::where('tenant_id', $tenantId)->delete();
        PortfolioCache::bump($tenantId);
    }

    /** Completa os campos derivados de um lançamento avulso. */
    private static function prepareData(array $data): array
    {
        $data['tenant_id'] = Filament::getTenant()->getKey();
        $data['asset_id'] = null;
        $data['quantity'] = 1;
        $data['direction'] = FlowDirection::defaultForType($data['type'] ?? '')->value;
        // source não é campo do form: criação usa o default 'manual' do banco
        // e edição preserva o valor existente (ex: 'recurring').

        return $data;
    }
}
