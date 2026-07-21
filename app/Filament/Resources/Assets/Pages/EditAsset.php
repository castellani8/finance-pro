<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Enums\FlowDirection;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Assets\Widgets\AssetStatsOverview;
use App\Models\Account;
use App\Models\Asset;
use App\Models\PortfolioSnapshot;
use App\Models\Transaction;
use App\Services\CurrencyConverter;
use App\Support\PortfolioCache;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAsset extends EditRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->redeemAction(),
            DeleteAction::make(),
        ];
    }

    /**
     * Resgate/vencimento com valor líquido final: zera a posição pelo valor
     * que realmente caiu e credita numa conta, se escolhida.
     */
    protected function redeemAction(): Action
    {
        return Action::make('redeem')
            ->label('Registrar resgate')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (): bool => in_array($this->getRecord()->type, ['FIXED_INCOME', ...Asset::PHYSICAL_TYPES], true)
                && $this->getRecord()->load('transactions')->positionQuantity() > 1e-9)
            ->modalHeading(fn (): string => 'Registrar resgate / venda de '.$this->getRecord()->name)
            ->modalDescription('Informe o valor LÍQUIDO que caiu (já descontado IR/IOF). A posição é zerada e, se você escolher uma conta, o dinheiro entra no saldo dela.')
            ->schema([
                DatePicker::make('date')
                    ->label('Data do resgate')
                    ->required()
                    ->default(now()),
                TextInput::make('net_amount')
                    ->label('Valor líquido recebido')
                    ->numeric()
                    ->minValue(0.01)
                    ->prefix(fn (): string => CurrencyConverter::symbol($this->getRecord()->currency))
                    ->required(),
                Select::make('account_id')
                    ->label('Creditar na conta (opcional)')
                    ->options(fn (): array => Account::query()
                        ->where('tenant_id', Filament::getTenant()->getKey())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),
            ])
            ->action(function (array $data): void {
                $asset = $this->getRecord()->load('transactions');
                $quantity = $asset->positionQuantity();

                Transaction::create([
                    'tenant_id' => Filament::getTenant()->getKey(),
                    'asset_id' => $asset->getKey(),
                    'account_id' => $data['account_id'] ?? null,
                    'type' => 'SELL',
                    'transaction_date' => $data['date'],
                    'quantity' => max(0.000001, $quantity),
                    'total_amount' => (float) $data['net_amount'],
                    'direction' => FlowDirection::Debit->value,
                    'movement' => 'Resgate / Vencimento',
                    'source' => 'manual',
                ]);

                $this->afterSave();

                Notification::make()
                    ->title('Resgate registrado')
                    ->body($data['account_id'] ?? null
                        ? 'Posição zerada e valor creditado na conta.'
                        : 'Posição zerada.')
                    ->success()
                    ->send();
            });
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
