<header class="fixed inset-x-0 top-0 z-50 border-b border-white/5 bg-neutral-950/80 backdrop-blur-md">
    <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <a href="{{ route('landing') }}" class="flex items-center">
            <img src="{{ asset('images/logo-dark.svg') }}" alt="Milia Invest" class="h-9 w-auto">
        </a>

        <div class="hidden items-center gap-8 text-sm font-medium text-neutral-300 md:flex">
            <a href="#recursos" class="transition hover:text-white">Recursos</a>
            <a href="#como-funciona" class="transition hover:text-white">Como funciona</a>
            <a href="#preco" class="transition hover:text-white">Plano</a>
            <a href="#faq" class="transition hover:text-white">Dúvidas</a>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ url('/app/login') }}" class="hidden text-sm font-medium text-neutral-300 transition hover:text-white sm:block">Entrar</a>
            <a href="{{ url('/app/register') }}" class="rounded-full bg-gold-500 px-4 py-2 text-sm font-semibold text-neutral-950 shadow-lg shadow-gold-500/20 transition hover:bg-gold-400">
                Testar grátis
            </a>
        </div>
    </nav>
</header>
