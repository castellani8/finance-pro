<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Milia Invest — Todo o seu patrimônio. Uma única visão.</title>
    <meta name="description" content="Acompanhe ações, FIIs, renda fixa, imóveis e veículos em um só painel: proventos, fluxo de caixa, relatório de IR e alertas automáticos. Teste grátis por {{ config('landing.plan.trial_days') }} dias, sem cartão.">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Milia Invest">
    <meta property="og:title" content="Milia Invest — Todo o seu patrimônio. Uma única visão.">
    <meta property="og:description" content="Investimentos, proventos, fluxo de caixa, relatório de IR e alertas automáticos em um só painel. {{ config('landing.plan.trial_days') }} dias grátis.">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:image" content="{{ asset('images/og-image.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ asset('images/og-image.png') }}">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/favicon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    <meta name="theme-color" content="#050505">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-neutral-950 font-sans text-neutral-100 antialiased">
    @include('landing.partials.nav')

    <main>
        @include('landing.partials.hero')
        @include('landing.partials.pains')
        @include('landing.partials.milha')
        @include('landing.partials.features')
        @include('landing.partials.how-it-works')
        @include('landing.partials.pricing')
        @include('landing.partials.faq')
        @include('landing.partials.cta')
    </main>

    @include('landing.partials.footer')

    {{-- Milha vendedora: chat de dúvidas que conduz ao cadastro --}}
    @livewire('milha-vendedora-chat')
</body>
</html>
