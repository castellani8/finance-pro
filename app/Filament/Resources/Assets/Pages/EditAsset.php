<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Enums\FlowDirection;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Assets\Widgets\AssetStatsOverview;
use App\Models\Account;
use App\Models\Asset;
use App\Models\B3ListedTicker;
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
            $this->exerciseAction(),
            $this->expireAction(),
            DeleteAction::make(),
        ];
    }

    /** Opção com posição em aberto (comprada ou lançada)? */
    private function hasOpenOptionPosition(): bool
    {
        return $this->getRecord()->isOption()
            && abs($this->getRecord()->load('transactions')->positionQuantity()) > 1e-9;
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

    /**
     * Exercício da opção: encerra a posição (comprada ou lançada) e lança a
     * ponta no ativo-objeto pelo strike. O prêmio realiza como resultado da
     * própria opção — assim o ciclo fecha igual ao extrato importado da B3,
     * em que a ação também entra/sai pelo strike.
     */
    protected function exerciseAction(): Action
    {
        return Action::make('exercise')
            ->label('Registrar exercício')
            ->icon('heroicon-o-arrows-right-left')
            ->color('warning')
            ->visible(fn (): bool => $this->hasOpenOptionPosition())
            ->modalHeading(fn (): string => 'Registrar exercício de '.$this->getRecord()->name)
            ->modalDescription('Encerra a posição na opção e lança a compra/venda do ativo-objeto pelo strike. O prêmio pago/recebido realiza como resultado da opção.')
            ->schema([
                DatePicker::make('date')
                    ->label('Data do exercício')
                    ->required()
                    ->default(now()),
                TextInput::make('quantity')
                    ->label('Quantidade exercida')
                    ->numeric()
                    ->minValue(0.000001)
                    ->default(fn (): float => abs($this->getRecord()->load('transactions')->positionQuantity()))
                    ->required(),
                Select::make('account_id')
                    ->label('Movimentar a conta (opcional)')
                    ->helperText('O valor do strike sai (compra) ou entra (venda) do saldo desta conta.')
                    ->options(fn (): array => Account::query()
                        ->where('tenant_id', Filament::getTenant()->getKey())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),
            ])
            ->action(function (array $data): void {
                $asset = $this->getRecord()->load('transactions');
                $position = $asset->positionQuantity();
                $strike = (float) ($asset->metadata['strike'] ?? 0);
                $underlyingTicker = mb_strtoupper(trim((string) ($asset->metadata['underlying'] ?? '')));

                if ($strike <= 0 || $underlyingTicker === '') {
                    Notification::make()
                        ->title('Complete os dados da opção')
                        ->body('Informe o ativo-objeto e o strike no cadastro da opção antes de registrar o exercício.')
                        ->danger()
                        ->send();

                    return;
                }

                $isLong = $position > 0;
                $quantity = min(abs($position), (float) $data['quantity']);

                if ($quantity <= 1e-9) {
                    return;
                }

                Transaction::create([
                    'tenant_id' => Filament::getTenant()->getKey(),
                    'asset_id' => $asset->getKey(),
                    'type' => 'EXERCISE',
                    'transaction_date' => $data['date'],
                    'quantity' => $quantity,
                    'total_amount' => 0,
                    'direction' => ($isLong ? FlowDirection::Debit : FlowDirection::Credit)->value,
                    'movement' => 'Exercício de opção',
                    'source' => 'manual',
                ]);

                // Call exercida: o titular compra e o lançador designado vende
                // pelo strike; put é o inverso.
                $isCall = mb_strtoupper((string) ($asset->metadata['option_type'] ?? 'CALL')) !== 'PUT';
                $underlyingBuys = $isCall === $isLong;
                $underlying = $this->resolveUnderlyingAsset($asset, $underlyingTicker);

                Transaction::create([
                    'tenant_id' => Filament::getTenant()->getKey(),
                    'asset_id' => $underlying->getKey(),
                    'account_id' => $data['account_id'] ?? null,
                    'type' => $underlyingBuys ? 'BUY' : 'SELL',
                    'transaction_date' => $data['date'],
                    'quantity' => $quantity,
                    'unit_price' => $strike,
                    'total_amount' => round($strike * $quantity, 4),
                    'direction' => ($underlyingBuys ? FlowDirection::Credit : FlowDirection::Debit)->value,
                    'movement' => "Exercício de opção {$asset->ticker_or_code}",
                    'source' => 'manual',
                ]);

                $this->afterSave();

                Notification::make()
                    ->title('Exercício registrado')
                    ->body(sprintf(
                        'Posição encerrada e %s de %s %s pelo strike.',
                        $underlyingBuys ? 'compra' : 'venda',
                        rtrim(rtrim(number_format($quantity, 6, ',', '.'), '0'), ','),
                        $underlyingTicker,
                    ))
                    ->success()
                    ->send();
            });
    }

    /** Vencimento sem exercício (virou pó): encerra a posição realizando o prêmio. */
    protected function expireAction(): Action
    {
        return Action::make('expire')
            ->label('Registrar vencimento (pó)')
            ->icon('heroicon-o-clock')
            ->color('gray')
            ->visible(fn (): bool => $this->hasOpenOptionPosition())
            ->modalHeading(fn (): string => 'Registrar vencimento de '.$this->getRecord()->name)
            ->modalDescription('A opção venceu sem exercício: a posição é encerrada e o prêmio realiza como resultado — perda para quem comprou, ganho para quem lançou.')
            ->schema([
                DatePicker::make('date')
                    ->label('Data do vencimento')
                    ->required()
                    ->default(fn () => $this->getRecord()->metadata['due_date'] ?? now()),
            ])
            ->action(function (array $data): void {
                $asset = $this->getRecord()->load('transactions');
                $position = $asset->positionQuantity();

                if (abs($position) <= 1e-9) {
                    return;
                }

                Transaction::create([
                    'tenant_id' => Filament::getTenant()->getKey(),
                    'asset_id' => $asset->getKey(),
                    'type' => 'EXPIRE',
                    'transaction_date' => $data['date'],
                    'quantity' => abs($position),
                    'total_amount' => 0,
                    'direction' => ($position > 0 ? FlowDirection::Debit : FlowDirection::Credit)->value,
                    'movement' => 'Vencimento sem exercício',
                    'source' => 'manual',
                ]);

                $this->afterSave();

                Notification::make()
                    ->title('Vencimento registrado')
                    ->body('Posição encerrada; o prêmio realizou como resultado.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Localiza (ou cria) o ativo-objeto do exercício pelo ticker. Ativos novos
     * entram como Ação — se for FII/unit, basta ajustar o tipo no cadastro.
     */
    private function resolveUnderlyingAsset(Asset $option, string $ticker): Asset
    {
        $underlying = Asset::firstOrNew([
            'tenant_id' => Filament::getTenant()->getKey(),
            'ticker_or_code' => $ticker,
        ]);

        if (! $underlying->exists) {
            $underlying->fill([
                'name' => B3ListedTicker::query()->where('ticker', $ticker)->value('name') ?? $ticker,
                'type' => 'STOCK',
                'currency' => $option->currency ?? 'BRL',
            ])->save();
        }

        return $underlying;
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
