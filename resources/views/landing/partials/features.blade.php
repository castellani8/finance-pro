<section id="recursos" class="scroll-mt-20 py-20 sm:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <p class="text-sm font-semibold tracking-widest text-gold-400 uppercase">Recursos</p>
            <h2 class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl">
                Tudo o que uma planilha nunca vai fazer por você
            </h2>
            <p class="mt-4 text-lg text-neutral-400">
                O Milia Invest trabalha em segundo plano: busca cotações, gera lançamentos recorrentes,
                tira fotos diárias da carteira e avisa quando algo precisa da sua atenção.
            </p>
        </div>

        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                [
                    'icon' => 'M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z',
                    'title' => 'Milha, sua assistente de IA',
                    'text' => '"Comprei 2 PETR4 a R$ 40 ontem" — ela registra, responde com gráficos e acompanha suas metas. Tudo com a sua aprovação.',
                ],
                [
                    'icon' => 'M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941',
                    'title' => 'Carteira 100% consolidada',
                    'text' => 'Ações, FIIs, opções, renda fixa, ouro, imóveis, veículos, máquinas e até colecionáveis — patrimônio inteiro, não só a corretora.',
                ],
                [
                    'icon' => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                    'title' => 'Cotações automáticas',
                    'text' => 'Preços da B3 e câmbio PTAX do Banco Central atualizados todos os dias, sem você digitar nada.',
                ],
                [
                    'icon' => 'M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                    'title' => 'Renda passiva organizada',
                    'text' => 'Dividendos, JCP e rendimentos por mês e por ativo. Veja sua renda passiva crescer de verdade.',
                ],
                [
                    'icon' => 'M3 6h18M7 12h10m-6 6h2',
                    'title' => 'Fluxo de caixa e recorrências',
                    'text' => 'Receitas contra despesas mês a mês. Aluguéis e assinaturas viram lançamentos automáticos no vencimento.',
                ],
                [
                    'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
                    'title' => 'Relatório pronto para o IR',
                    'text' => 'Bens e direitos, proventos e ganhos do ano organizados no formato da declaração. Abril deixa de ser um problema.',
                ],
                [
                    'icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
                    'title' => 'Você contra os benchmarks',
                    'text' => 'Sua carteira comparada com os mesmos aportes rendendo 100% do CDI e com o IBOV. Saiba se está valendo a pena.',
                ],
                [
                    'icon' => 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0',
                    'title' => 'Alertas que trabalham por você',
                    'text' => 'Vencimentos de renda fixa, contratos terminando, contas negativas: aviso no painel e no seu e-mail, todo dia às 8h.',
                ],
                [
                    'icon' => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M13.5 9h4.125c.621 0 1.125.504 1.125 1.125V21',
                    'title' => 'Pessoa física e empresas',
                    'text' => 'Separe o patrimônio pessoal das suas empresas e veja tudo junto quando quiser — com histórico de auditoria completo.',
                ],
            ] as $feature)
                <div class="group rounded-2xl border border-neutral-800 bg-neutral-900/60 p-6 transition hover:border-gold-500/40 hover:bg-neutral-900">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gold-500/10 text-gold-400 ring-1 ring-gold-500/20 transition group-hover:bg-gold-500/20">
                        <svg class="h-5.5 w-5.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $feature['icon'] }}"/>
                        </svg>
                    </div>
                    <h3 class="mt-4 font-semibold text-white">{{ $feature['title'] }}</h3>
                    <p class="mt-2 text-sm leading-6 text-neutral-400">{{ $feature['text'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
