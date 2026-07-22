<section id="faq" class="scroll-mt-20 border-y border-neutral-800 bg-neutral-900/50 py-20 sm:py-28">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <p class="text-sm font-semibold tracking-widest text-gold-400 uppercase">Dúvidas frequentes</p>
            <h2 class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl">O que você precisa saber antes de começar</h2>
        </div>

        <div class="mt-12 space-y-4">
            @foreach ([
                [
                    'q' => 'Preciso informar cartão de crédito para testar?',
                    'a' => 'Não. Você cria a conta e usa todos os recursos por '.config('landing.plan.trial_days').' dias sem informar nenhuma forma de pagamento. Só paga se decidir continuar.',
                ],
                [
                    'q' => 'Quais ativos posso acompanhar?',
                    'a' => 'Ações e opções da B3, fundos imobiliários, renda fixa, ouro e commodities, imóveis, veículos, máquinas, softwares e até colecionáveis. Ativos negociados em bolsa têm cotação atualizada automaticamente; os demais você avalia como preferir.',
                ],
                [
                    'q' => 'O que a Milha, a assistente de IA, consegue fazer?',
                    'a' => 'Você conversa com ela em português normal: "comprei 2 PETR4 a R$ 40 ontem", "quanto recebi de FIIs esse ano?", "assino Netflix, vence dia 10". Ela registra operações, despesas, recorrências, contas e ativos (sempre pedindo sua confirmação antes), responde perguntas com os seus números reais e desenha gráficos na própria conversa. Ela nunca recomenda investimentos.',
                ],
                [
                    'q' => 'Meus dados financeiros estão seguros?',
                    'a' => 'Sim. Seus dados ficam isolados na sua conta, com registro de auditoria de todas as alterações. Em conformidade com a LGPD, você pode exportar ou excluir tudo a qualquer momento na página "Privacidade e dados".',
                ],
                [
                    'q' => 'Como funciona o relatório para o Imposto de Renda?',
                    'a' => 'O Milia Invest consolida bens e direitos, proventos recebidos e ganhos do ano no formato que a declaração pede. Você escolhe o ano e copia os valores — sem caçar informes em abril.',
                ],
                [
                    'q' => 'O Milia Invest recomenda investimentos?',
                    'a' => 'Não. O Milia Invest é uma ferramenta de organização e acompanhamento patrimonial. Os dados têm caráter informativo e as decisões de investimento são sempre suas.',
                ],
                [
                    'q' => 'Posso cancelar quando quiser?',
                    'a' => 'Sim, a qualquer momento, direto no painel e sem multa. E se cancelar, seus dados continuam disponíveis para exportação.',
                ],
            ] as $item)
                <details class="group rounded-2xl border border-neutral-800 bg-neutral-900 p-6 transition open:border-gold-500/30">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-white [&::-webkit-details-marker]:hidden">
                        {{ $item['q'] }}
                        <svg class="h-5 w-5 shrink-0 text-gold-400 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                        </svg>
                    </summary>
                    <p class="mt-4 text-sm leading-7 text-neutral-400">{{ $item['a'] }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
