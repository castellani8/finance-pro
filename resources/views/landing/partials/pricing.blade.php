<section id="preco" class="relative scroll-mt-20 overflow-hidden py-20 sm:py-28">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div class="absolute top-1/2 left-1/2 h-125 w-175 -translate-x-1/2 -translate-y-1/2 rounded-full bg-gold-500/8 blur-3xl"></div>
    </div>

    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <p class="text-sm font-semibold tracking-widest text-gold-400 uppercase">Plano único, sem pegadinha</p>
            <h2 class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl">
                Experimente tudo antes de pagar qualquer coisa
            </h2>
            <p class="mt-4 text-lg text-neutral-400">
                Um único plano com todos os recursos liberados. Use por {{ config('landing.plan.trial_days') }} dias,
                veja seu patrimônio organizado e só então decida se vale a pena.
            </p>
        </div>

        <div class="mx-auto mt-14 max-w-lg">
            <div class="relative rounded-3xl border border-gold-500/30 bg-neutral-900 p-8 shadow-2xl shadow-gold-500/10 sm:p-10">
                <span class="absolute -top-4 left-1/2 -translate-x-1/2 rounded-full bg-gold-500 px-4 py-1 text-sm font-bold text-neutral-950">
                    {{ config('landing.plan.trial_days') }} dias grátis
                </span>

                <div class="text-center">
                    <h3 class="text-lg font-semibold text-white">Milia Invest completo</h3>
                    <div class="mt-4 flex items-baseline justify-center gap-1">
                        <span class="text-xl font-medium text-neutral-400">R$</span>
                        <span class="text-6xl font-bold tracking-tight text-white">{{ config('landing.plan.price') }}</span>
                        <span class="text-lg text-neutral-400">/mês</span>
                    </div>
                    <p class="mt-2 text-sm text-neutral-400">Menos de R$ 0,70 por dia para nunca mais perder o controle.</p>
                </div>

                <ul class="mt-8 space-y-3 text-sm text-neutral-300">
                    @foreach ([
                        'Milha, a assistente de IA — registre e consulte tudo por conversa',
                        'Ativos ilimitados em todas as classes',
                        'Cotações da B3 e câmbio atualizados diariamente',
                        'Renda passiva, fluxo de caixa e recorrências',
                        'Relatório anual pronto para o Imposto de Renda',
                        'Comparação da carteira com CDI e IBOV',
                        'Alertas automáticos no painel e por e-mail',
                        'Patrimônio pessoal e de empresas separados',
                        'Exportação e exclusão dos seus dados quando quiser (LGPD)',
                    ] as $item)
                        <li class="flex items-start gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-gold-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/>
                            </svg>
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>

                <a href="{{ url('/app/register') }}" class="mt-9 block rounded-full bg-gold-500 px-8 py-4 text-center text-base font-semibold text-neutral-950 shadow-xl shadow-gold-500/25 transition hover:scale-[1.02] hover:bg-gold-400">
                    Começar meus {{ config('landing.plan.trial_days') }} dias grátis
                </a>
                <p class="mt-4 text-center text-xs text-neutral-500">
                    Sem cartão de crédito para testar · Cancele quando quiser, sem multa
                </p>
            </div>
        </div>
    </div>
</section>
