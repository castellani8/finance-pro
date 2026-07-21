<footer class="border-t border-neutral-800 bg-neutral-950">
    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="grid gap-10 md:grid-cols-4">
            <div class="md:col-span-2">
                <img src="{{ asset('images/logo-dark.svg') }}" alt="Milia Invest" class="h-9 w-auto">
                <p class="mt-4 max-w-sm text-sm leading-6 text-neutral-400">
                    Gestão completa do seu patrimônio: investimentos, proventos, fluxo de caixa,
                    relatório de IR e alertas automáticos em um só painel.
                </p>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-white">Produto</h3>
                <ul class="mt-4 space-y-2.5 text-sm text-neutral-400">
                    <li><a href="#recursos" class="transition hover:text-gold-400">Recursos</a></li>
                    <li><a href="#como-funciona" class="transition hover:text-gold-400">Como funciona</a></li>
                    <li><a href="#preco" class="transition hover:text-gold-400">Plano e preço</a></li>
                    <li><a href="{{ url('/app/login') }}" class="transition hover:text-gold-400">Entrar</a></li>
                    <li><a href="{{ url('/app/register') }}" class="transition hover:text-gold-400">Criar conta</a></li>
                    <li><a href="{{ route('legal.privacidade') }}" class="transition hover:text-gold-400">Política de Privacidade</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-white">Contato</h3>
                <ul class="mt-4 space-y-2.5 text-sm text-neutral-400">
                    <li>
                        <a href="mailto:{{ config('landing.contact.email') }}" class="transition hover:text-gold-400">
                            {{ config('landing.contact.email') }}
                        </a>
                    </li>
                    <li>
                        <a href="{{ config('landing.contact.whatsapp_url') }}" target="_blank" rel="noopener" class="transition hover:text-gold-400">
                            WhatsApp: {{ config('landing.contact.whatsapp') }}
                        </a>
                    </li>
                    @if (config('landing.contact.instagram_url'))
                        <li><a href="{{ config('landing.contact.instagram_url') }}" target="_blank" rel="noopener" class="transition hover:text-gold-400">Instagram</a></li>
                    @endif
                    @if (config('landing.contact.linkedin_url'))
                        <li><a href="{{ config('landing.contact.linkedin_url') }}" target="_blank" rel="noopener" class="transition hover:text-gold-400">LinkedIn</a></li>
                    @endif
                </ul>
            </div>
        </div>

        <div class="mt-12 border-t border-neutral-800 pt-8 text-center text-xs leading-6 text-neutral-500">
            <p>
                O Milia Invest não faz recomendação de investimento. Os dados exibidos têm caráter informativo
                e podem conter atrasos ou imprecisões.
            </p>
            <p class="mt-2">&copy; {{ date('Y') }} Milia Invest. Todos os direitos reservados.</p>
        </div>
    </div>
</footer>
