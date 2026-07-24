<x-filament-panels::page>
    @php($agenda = $this->getAgenda())
    @php($money = fn (float $v): string => 'R$ '.number_format($v, 2, ',', '.'))

    <style>
        .ag-nav { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
        .ag-month { font-size: 1.125rem; font-weight: 700; }
        .ag-totais { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: .75rem; }
        .ag-total { border: 1px solid rgba(128,128,128,.25); border-radius: .75rem; padding: .875rem 1rem; }
        .ag-total small { display: block; opacity: .6; font-size: .75rem; margin-bottom: .25rem; }
        .ag-total strong { font-size: 1.125rem; }
        .ag-day { display: flex; gap: 1rem; padding: .75rem 0; border-top: 1px solid rgba(128,128,128,.15); }
        .ag-day:first-child { border-top: 0; }
        .ag-date { min-width: 3.25rem; text-align: center; }
        .ag-date b { display: block; font-size: 1.25rem; line-height: 1.1; }
        .ag-date span { font-size: .7rem; opacity: .55; text-transform: uppercase; }
        .ag-event { display: flex; justify-content: space-between; gap: 1rem; align-items: baseline; padding: .2rem 0; }
        .ag-event .ag-label { font-weight: 600; font-size: .875rem; }
        .ag-event .ag-detail { font-size: .75rem; opacity: .6; }
        .ag-amount { font-weight: 700; font-size: .875rem; white-space: nowrap; }
        .ag-badge { font-size: .65rem; border-radius: 999px; padding: .1rem .5rem; margin-left: .4rem; vertical-align: middle; }
        .ag-provento { background: rgba(212,175,55,.15); color: #B18F27; }
        .ag-receita { background: rgba(34,197,94,.15); color: #16a34a; }
        .ag-despesa { background: rgba(239,68,68,.15); color: #ef4444; }
        .ag-vencimento { background: rgba(59,130,246,.15); color: #3b82f6; }
    </style>

    <div class="ag-nav">
        <x-filament::button color="gray" size="sm" wire:click="previousMonth" :disabled="$this->isFirstMonth()">
            ← Anterior
        </x-filament::button>
        <span class="ag-month">{{ $this->getMonthLabel() }}</span>
        <x-filament::button color="gray" size="sm" wire:click="nextMonth" :disabled="$this->isLastMonth()">
            Próximo →
        </x-filament::button>
    </div>

    <div class="ag-totais">
        <div class="ag-total">
            <small>Previsto a receber</small>
            <strong style="color:#16a34a">{{ $money($agenda['totals']['a_receber']) }}</strong>
        </div>
        <div class="ag-total">
            <small>Despesas previstas</small>
            <strong style="color:#ef4444">{{ $money($agenda['totals']['a_pagar']) }}</strong>
        </div>
        <div class="ag-total">
            <small>Renda fixa vencendo</small>
            <strong style="color:#3b82f6">{{ $money($agenda['totals']['vencendo']) }}</strong>
        </div>
    </div>

    <x-filament::section heading="Linha do tempo"
        description="Recorrências e vencimentos são contratuais; proventos são estimados pelo histórico de pagamento de cada ativo.">
        @if ($agenda['events'] === [])
            <p style="font-size:.875rem; opacity:.6; padding:.5rem 0;">
                Nenhum evento previsto para este mês. Cadastre recorrências (aluguéis,
                assinaturas) e mantenha os proventos importados para a agenda ganhar vida.
            </p>
        @else
            @foreach (collect($agenda['events'])->groupBy('day') as $day => $events)
                <div class="ag-day">
                    <div class="ag-date">
                        <b>{{ str_pad((string) $day, 2, '0', STR_PAD_LEFT) }}</b>
                        <span>{{ \Illuminate\Support\Carbon::parse($events->first()['date'])->locale('pt_BR')->translatedFormat('D') }}</span>
                    </div>
                    <div style="flex:1">
                        @foreach ($events as $event)
                            <div class="ag-event">
                                <div>
                                    <span class="ag-label">{{ $event['label'] }}</span>
                                    <span class="ag-badge ag-{{ $event['kind'] }}">
                                        {{ ['provento' => 'Provento', 'receita' => 'Receita', 'despesa' => 'Despesa', 'vencimento' => 'Vencimento'][$event['kind']] }}{{ $event['estimated'] ? ' · estimado' : '' }}
                                    </span>
                                    @if ($event['detail'])
                                        <div class="ag-detail">{{ $event['detail'] }}</div>
                                    @endif
                                </div>
                                <span class="ag-amount" style="color: {{ $event['kind'] === 'despesa' ? '#ef4444' : '#16a34a' }}">
                                    {{ $event['kind'] === 'despesa' ? '−' : '+' }}{{ $money((float) $event['amount']) }}{{ $event['estimated'] ? ' ~' : '' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </x-filament::section>
</x-filament-panels::page>
