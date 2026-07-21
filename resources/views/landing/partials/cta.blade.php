<section class="relative overflow-hidden py-20 sm:py-28">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div class="absolute -bottom-40 left-1/2 h-100 w-175 -translate-x-1/2 rounded-full bg-gold-500/10 blur-3xl"></div>
    </div>

    <div class="relative mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
        <img src="{{ asset('images/logo-icon.svg') }}" alt="" class="mx-auto h-14 w-14" aria-hidden="true">
        <h2 class="mt-6 text-3xl font-bold tracking-tight text-white sm:text-4xl">
            Daqui a {{ config('landing.plan.trial_days') }} dias, você pode continuar no escuro —
            <span class="bg-gradient-to-r from-gold-300 to-gold-500 bg-clip-text text-transparent">ou com o patrimônio inteiro sob controle.</span>
        </h2>
        <p class="mt-4 text-lg text-neutral-400">
            O teste é grátis, não pede cartão e leva 2 minutos para começar.
        </p>
        <div class="mt-8">
            <a href="{{ url('/app/register') }}" class="inline-block rounded-full bg-gold-500 px-10 py-4 text-base font-semibold text-neutral-950 shadow-xl shadow-gold-500/25 transition hover:scale-105 hover:bg-gold-400">
                Criar minha conta grátis
            </a>
        </div>
    </div>
</section>
