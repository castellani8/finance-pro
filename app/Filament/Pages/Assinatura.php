<?php

namespace App\Filament\Pages;

use App\Enums\SubscriptionStatus;
use App\Exceptions\AsaasException;
use App\Exceptions\PaymentGatewayException;
use App\Models\Subscription;
use App\Services\Payments\Data\CardDetails;
use App\Services\Payments\Data\PayerDetails;
use App\Services\Payments\Data\Plan;
use App\Services\Payments\Enums\BillingType;
use App\Services\Payments\PaymentGatewayManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * Central da assinatura: status, dias restantes do teste, plano único e o
 * checkout transparente (PIX, boleto ou cartão) via gateway de pagamento.
 */
class Assinatura extends Page
{
    protected string $view = 'filament.pages.assinatura';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|\UnitEnum|null $navigationGroup = 'Minha conta';

    protected static ?string $navigationLabel = 'Assinatura';

    protected static ?string $title = 'Assinatura';

    protected static ?int $navigationSort = 95;

    public function getSubscription(): ?Subscription
    {
        return auth()->user()?->subscription;
    }

    public function getPlan(): Plan
    {
        return Plan::find(config('subscription.default_plan'));
    }

    public function getPlanPrice(): string
    {
        return number_format($this->getPlan()->price, 2, ',', '.');
    }

    /**
     * @return list<string>
     */
    public function getPlanFeatures(): array
    {
        return [
            'Ativos ilimitados em todas as classes',
            'Cotações da B3 e câmbio atualizados diariamente',
            'Renda passiva, fluxo de caixa e recorrências',
            'Relatório anual pronto para o Imposto de Renda',
            'Comparação da carteira com CDI e IBOV',
            'Alertas automáticos no painel e por e-mail',
            'Cancele quando quiser, sem multa',
        ];
    }

    /** A assinatura pode ser contratada (não há cobrança recorrente ativa)? */
    public function canSubscribe(): bool
    {
        $subscription = $this->getSubscription();

        return $subscription !== null
            && ! in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)
            && ! ($subscription->status === SubscriptionStatus::Trialing && $subscription->asaas_subscription_id !== null);
    }

    public function canCancel(): bool
    {
        $subscription = $this->getSubscription();

        return $subscription?->asaas_subscription_id !== null
            && in_array($subscription->status, [
                SubscriptionStatus::Trialing,
                SubscriptionStatus::Active,
                SubscriptionStatus::PastDue,
            ], true);
    }

    public function subscribeAction(): Action
    {
        return Action::make('subscribe')
            ->label('Assinar agora')
            ->icon('heroicon-o-credit-card')
            ->color('primary')
            ->size('lg')
            ->visible(fn (): bool => $this->canSubscribe())
            ->modalHeading('Assinar o Milia Invest')
            ->modalDescription('R$ '.$this->getPlanPrice().'/mês, renovação automática. No PIX e boleto o acesso é liberado assim que o pagamento for confirmado.')
            ->modalSubmitActionLabel('Confirmar assinatura')
            ->schema([
                Select::make('billing_type')
                    ->label('Forma de pagamento')
                    ->options(collect(BillingType::cases())->mapWithKeys(
                        fn (BillingType $type) => [$type->value => $type->label()],
                    )->all())
                    ->default(BillingType::PIX->value)
                    ->required()
                    ->native(false)
                    ->live(),
                TextInput::make('holder_cpf_cnpj')
                    ->label('CPF ou CNPJ do titular')
                    ->required()
                    ->maxLength(18),
                Grid::make(['default' => 2])
                    ->visible(fn (Get $get): bool => $get('billing_type') === BillingType::CREDIT_CARD->value)
                    ->schema([
                        TextInput::make('holder_name')
                            ->label('Nome impresso no cartão')
                            ->required(fn (Get $get): bool => $get('billing_type') === BillingType::CREDIT_CARD->value)
                            ->columnSpan(['default' => 2]),
                        TextInput::make('number')
                            ->label('Número do cartão')
                            ->required(fn (Get $get): bool => $get('billing_type') === BillingType::CREDIT_CARD->value)
                            ->maxLength(19)
                            ->columnSpan(['default' => 2]),
                        TextInput::make('expiry')
                            ->label('Validade (MM/AA)')
                            ->placeholder('12/28')
                            ->required(fn (Get $get): bool => $get('billing_type') === BillingType::CREDIT_CARD->value)
                            ->maxLength(5),
                        TextInput::make('cvv')
                            ->label('CVV')
                            ->required(fn (Get $get): bool => $get('billing_type') === BillingType::CREDIT_CARD->value)
                            ->maxLength(4),
                        TextInput::make('holder_postal_code')
                            ->label('CEP de cobrança')
                            ->required(fn (Get $get): bool => $get('billing_type') === BillingType::CREDIT_CARD->value),
                        TextInput::make('holder_address_number')
                            ->label('Número do endereço')
                            ->required(fn (Get $get): bool => $get('billing_type') === BillingType::CREDIT_CARD->value),
                    ]),
            ])
            ->action(function (array $data): void {
                $this->processSubscription($data);
            });
    }

    public function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancelar assinatura')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->link()
            ->visible(fn (): bool => $this->canCancel())
            ->requiresConfirmation()
            ->modalHeading('Cancelar assinatura')
            ->modalDescription('A cobrança recorrente é interrompida. Se houver período já pago, seu acesso continua até o fim dele — e seus dados ficam guardados.')
            ->modalSubmitActionLabel('Sim, cancelar')
            ->action(function (): void {
                $subscription = $this->getSubscription();

                try {
                    app(PaymentGatewayManager::class)
                        ->driver($subscription->gateway ?? config('subscription.gateway'))
                        ->cancelSubscription($subscription->asaas_subscription_id);
                } catch (AsaasException $e) {
                    Notification::make()->title('Não foi possível cancelar')->body($e->friendlyMessage())->danger()->send();

                    return;
                } catch (PaymentGatewayException) {
                    Notification::make()->title('Não foi possível cancelar')->body('Tente novamente em instantes.')->danger()->send();

                    return;
                }

                $subscription->update([
                    'status' => match (true) {
                        $subscription->current_period_ends_at?->isFuture() ?? false => SubscriptionStatus::Canceled,
                        $subscription->trial_ends_at->isFuture() => SubscriptionStatus::Trialing,
                        default => SubscriptionStatus::Expired,
                    },
                    'canceled_at' => now(),
                ]);

                Notification::make()->title('Assinatura cancelada')->success()->send();
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function processSubscription(array $data): void
    {
        $user = auth()->user();
        $subscription = $this->getSubscription();
        $billingType = BillingType::from($data['billing_type']);
        $plan = $this->getPlan();

        $payer = new PayerDetails(
            name: $billingType === BillingType::CREDIT_CARD ? trim($data['holder_name']) : $user->name,
            email: $user->email,
            cpfCnpj: preg_replace('/\D/', '', $data['holder_cpf_cnpj']),
            phone: preg_replace('/\D/', '', (string) $user->phone),
            postalCode: isset($data['holder_postal_code']) ? preg_replace('/\D/', '', $data['holder_postal_code']) : null,
            addressNumber: $data['holder_address_number'] ?? null,
        );

        $card = null;

        if ($billingType === BillingType::CREDIT_CARD) {
            [$month, $year] = array_pad(explode('/', $data['expiry'] ?? ''), 2, '');

            $card = CardDetails::fromArray([
                'holder_name' => $data['holder_name'],
                'number' => $data['number'],
                'expiry_month' => trim($month),
                'expiry_year' => trim($year),
                'cvv' => $data['cvv'],
            ]);
        }

        try {
            $result = app(PaymentGatewayManager::class)
                ->driver(config('subscription.gateway'))
                ->subscribe($user, $plan, $billingType, $payer, $card, request()->ip());
        } catch (AsaasException $e) {
            Notification::make()->title('Não foi possível concluir a assinatura')->body($e->friendlyMessage())->danger()->send();

            return;
        } catch (PaymentGatewayException) {
            Notification::make()->title('Não foi possível concluir a assinatura')->body('Verifique os dados e tente novamente.')->danger()->send();

            return;
        }

        $subscription->update([
            'gateway' => $result->gateway,
            'asaas_customer_id' => $result->customerId,
            'asaas_subscription_id' => $result->subscriptionId,
            'billing_type' => $billingType->value,
            'latest_invoice_url' => $result->invoiceUrl,
            'canceled_at' => null,
            // Cartão é cobrado na hora: libera o acesso já; o período pago é
            // materializado pelo webhook PAYMENT_CONFIRMED. PIX/boleto seguem
            // como estão até o pagamento ser confirmado.
            ...($billingType === BillingType::CREDIT_CARD && $result->status === SubscriptionStatus::Active
                ? ['status' => SubscriptionStatus::Active]
                : []),
        ]);

        if ($billingType === BillingType::CREDIT_CARD) {
            Notification::make()
                ->title('Assinatura ativada!')
                ->body('Pagamento aprovado no cartão. Bons investimentos!')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Assinatura criada — falta o pagamento')
            ->body('Abra a fatura para pagar por '.$billingType->label().'. Seu acesso é liberado na confirmação.')
            ->warning()
            ->persistent()
            ->send();

        if ($result->invoiceUrl) {
            redirect()->away($result->invoiceUrl);
        }
    }
}
