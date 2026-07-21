<x-filament-panels::page>
    @php
        $subscription = $this->getSubscription();
        $status = $subscription?->status;
    @endphp

    <div class="mi-sub">
        {{-- Status atual --}}
        <x-filament::section>
            <x-slot name="heading">Sua assinatura</x-slot>

            @if ($subscription === null)
                <p style="color: rgb(113 113 122)">Sua conta não possui registro de assinatura. Fale com o suporte.</p>
            @else
                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 1rem 2rem">
                    <div>
                        <div style="font-size: .75rem; text-transform: uppercase; letter-spacing: .08em; color: rgb(113 113 122); margin-bottom: .35rem">Status</div>
                        <x-filament::badge :color="$status->color()" size="lg">
                            {{ $status->label() }}
                        </x-filament::badge>
                    </div>

                    @if ($subscription->isTrialing())
                        <div>
                            <div style="font-size: .75rem; text-transform: uppercase; letter-spacing: .08em; color: rgb(113 113 122); margin-bottom: .35rem">Teste grátis termina em</div>
                            <div style="font-size: 1.35rem; font-weight: 700">
                                {{ $subscription->trialDaysLeft() }} {{ $subscription->trialDaysLeft() === 1 ? 'dia' : 'dias' }}
                                <span style="font-size: .85rem; font-weight: 400; color: rgb(113 113 122)">({{ $subscription->trial_ends_at->format('d/m/Y') }})</span>
                            </div>
                        </div>
                    @elseif ($subscription->current_period_ends_at !== null)
                        <div>
                            <div style="font-size: .75rem; text-transform: uppercase; letter-spacing: .08em; color: rgb(113 113 122); margin-bottom: .35rem">Período atual até</div>
                            <div style="font-size: 1.35rem; font-weight: 700">{{ $subscription->current_period_ends_at->format('d/m/Y') }}</div>
                        </div>
                    @endif
                </div>

                @unless ($subscription->hasAccess())
                    <div style="margin-top: 1rem; padding: .875rem 1rem; border-radius: .5rem; border: 1px solid rgb(212 175 55 / .4); background: rgb(212 175 55 / .08); font-size: .875rem">
                        Seu acesso ao painel está pausado. Assine para continuar acompanhando seu patrimônio — todos os seus dados estão guardados.
                    </div>
                @endunless
            @endif
        </x-filament::section>

        {{-- Plano --}}
        <x-filament::section>
            <x-slot name="heading">Plano único — Milia Invest completo</x-slot>

            <div style="display: flex; align-items: baseline; gap: .35rem; margin-bottom: 1rem">
                <span style="font-size: 1rem; color: rgb(113 113 122)">R$</span>
                <span style="font-size: 2.5rem; font-weight: 800; letter-spacing: -0.02em">{{ $this->getPlanPrice() }}</span>
                <span style="color: rgb(113 113 122)">/mês</span>
            </div>

            <ul style="display: grid; gap: .5rem; margin-bottom: 1.5rem">
                @foreach ($this->getPlanFeatures() as $feature)
                    <li style="display: flex; gap: .5rem; align-items: flex-start; font-size: .875rem">
                        <span style="color: #D4AF37; font-weight: 700">✓</span> {{ $feature }}
                    </li>
                @endforeach
            </ul>

            <x-filament::button color="primary" size="lg" disabled title="Pagamento online em breve">
                Assinar agora
            </x-filament::button>
            <p style="margin-top: .75rem; font-size: .8125rem; color: rgb(113 113 122)">
                O pagamento online (Pix, boleto e cartão) está chegando. Enquanto isso, fale com a gente em
                <a href="mailto:{{ config('landing.contact.email') }}" style="color: #D4AF37; text-decoration: underline">{{ config('landing.contact.email') }}</a>
                para ativar sua assinatura.
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
