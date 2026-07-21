<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $resubscribed ? 'Inscrição reativada' : 'Descadastro confirmado' }} — Milia Invest</title>
    <meta name="robots" content="noindex">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/favicon.svg') }}">
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-neutral-950 px-4 font-sans text-neutral-100 antialiased">
    <main class="w-full max-w-md rounded-2xl border border-gold-500/30 bg-neutral-900 p-8 text-center shadow-2xl shadow-gold-500/10">
        <img src="{{ asset('images/logo-dark.svg') }}" alt="Milia Invest" class="mx-auto h-10 w-auto">

        @if ($resubscribed)
            <h1 class="mt-6 text-xl font-bold text-white">Inscrição reativada ✓</h1>
            <p class="mt-3 text-sm leading-6 text-neutral-400">
                Você voltará a receber nossas dicas e avisos sobre a sua conta, {{ str($user->name)->before(' ') }}.
            </p>
            <form method="POST" action="{{ URL::signedRoute('marketing.unsubscribe', ['user' => $user]) }}" class="mt-6">
                @csrf
                <button type="submit" class="text-sm text-neutral-500 underline transition hover:text-gold-400">
                    Cancelar de novo
                </button>
            </form>
        @else
            <h1 class="mt-6 text-xl font-bold text-white">Descadastro confirmado</h1>
            <p class="mt-3 text-sm leading-6 text-neutral-400">
                Você não receberá mais e-mails de marketing da Milia Invest, {{ str($user->name)->before(' ') }}.
                Avisos essenciais da conta (alertas da carteira que você configurou) continuam normalmente.
            </p>
            <a href="{{ URL::signedRoute('marketing.resubscribe', ['user' => $user]) }}"
                class="mt-6 inline-block rounded-full bg-gold-500 px-6 py-2.5 text-sm font-semibold text-neutral-950 transition hover:bg-gold-400">
                Foi um engano — quero voltar a receber
            </a>
        @endif

        <p class="mt-8 text-xs text-neutral-600">
            <a href="{{ url('/') }}" class="underline hover:text-neutral-400">miliainvest</a> ·
            <a href="{{ route('legal.privacidade') }}" class="underline hover:text-neutral-400">Privacidade</a>
        </p>
    </main>
</body>
</html>
