<section class="relative overflow-hidden pt-32 pb-16 sm:pt-40 sm:pb-24">
    {{-- brilhos decorativos --}}
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div class="absolute -top-40 left-1/2 h-125 w-200 -translate-x-1/2 rounded-full bg-gold-500/10 blur-3xl"></div>
        <div class="absolute top-100 -left-40 h-100 w-100 rounded-full bg-gold-500/5 blur-3xl"></div>
    </div>

    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-3xl text-center">
            <span class="inline-flex items-center gap-2 rounded-full border border-gold-500/30 bg-gold-500/10 px-4 py-1.5 text-sm font-medium text-gold-300">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                {{ config('landing.plan.trial_days') }} dias grátis — sem cartão de crédito
            </span>

            <h1 class="mt-6 text-4xl font-bold tracking-tight text-white sm:text-6xl">
                Todo o seu patrimônio.
                <span class="bg-gradient-to-r from-gold-300 to-gold-500 bg-clip-text text-transparent">Uma única visão.</span>
            </h1>

            <p class="mt-6 text-lg leading-8 text-neutral-300 sm:text-xl">
                Ações, FIIs, renda fixa, imóveis, veículos e até ouro — acompanhe rentabilidade,
                proventos e fluxo de caixa em um painel que atualiza as cotações por você, todos os dias.
            </p>

            <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                <a href="{{ url('/app/register') }}" class="w-full rounded-full bg-gold-500 px-8 py-3.5 text-center text-base font-semibold text-neutral-950 shadow-xl shadow-gold-500/25 transition hover:scale-105 hover:bg-gold-400 sm:w-auto">
                    Começar meus {{ config('landing.plan.trial_days') }} dias grátis
                </a>
                <a href="{{ url('/app/login') }}" class="w-full rounded-full border border-neutral-700 px-8 py-3.5 text-center text-base font-semibold text-neutral-200 transition hover:border-neutral-500 hover:text-white sm:w-auto">
                    Já tenho conta
                </a>
            </div>

            <ul class="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-neutral-400">
                <li class="flex items-center gap-1.5"><span class="text-gold-400">✓</span> Milha, assistente de IA que anota tudo por você</li>
                <li class="flex items-center gap-1.5"><span class="text-gold-400">✓</span> Cotações da B3 e câmbio automáticos</li>
                <li class="flex items-center gap-1.5"><span class="text-gold-400">✓</span> Relatório pronto para o IR</li>
            </ul>
        </div>

        <div class="relative mx-auto mt-16 max-w-5xl">
            <div class="absolute -inset-4 rounded-3xl bg-gradient-to-r from-gold-500/20 via-gold-400/5 to-gold-500/10 blur-2xl" aria-hidden="true"></div>
            <img src="{{ asset('images/hero-dashboard.svg') }}" alt="Painel do Milia Invest com patrimônio total, evolução da carteira e alocação por classe de ativo"
                class="relative w-full rounded-2xl border border-neutral-700/60 shadow-2xl shadow-neutral-950/80">
        </div>
    </div>
</section>
