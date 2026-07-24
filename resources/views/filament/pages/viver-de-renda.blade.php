<x-filament-panels::page>
    @php($p = $this->getProjection())
    @php($money = fn (float $v): string => 'R$ '.number_format($v, 2, ',', '.'))
    @php($moneyShort = fn (float $v): string => 'R$ '.number_format($v, 0, ',', '.'))

    <style>
        .vr-resumo { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: .75rem; margin-bottom: 1rem; }
        .vr-card { border: 1px solid rgba(128,128,128,.25); border-radius: .75rem; padding: .875rem 1rem; }
        .vr-card small { display: block; opacity: .6; font-size: .72rem; margin-bottom: .25rem; }
        .vr-card strong { font-size: 1.05rem; }
        .vr-toggle { display: flex; gap: .5rem; }
        .vr-wrap { overflow: auto; max-height: 30rem; }
        .vr-table { width: 100%; border-collapse: collapse; font-size: .8rem; }
        .vr-table th { position: sticky; top: 0; background: var(--vr-bg, inherit); text-align: right; padding: .5rem .6rem; font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; opacity: .7; border-bottom: 1px solid rgba(128,128,128,.3); }
        .vr-table th:first-child { text-align: left; }
        .vr-table td { text-align: right; padding: .45rem .6rem; border-bottom: 1px solid rgba(128,128,128,.12); white-space: nowrap; }
        .vr-table td:first-child { text-align: left; font-weight: 700; text-transform: capitalize; }
        .vr-marco td { background: rgba(212,175,55,.12); font-weight: 700; }
        .vr-ok { color: #16a34a; font-weight: 600; }
        .vr-juros { color: #B18F27; }
    </style>

    @if ($p['configurado'] && $p['resumo'] !== null)
        <x-filament::section heading="Resultados da projeção"
            description="Aporte reajustado em {{ number_format($p['reajuste_aporte_anual'], 1, ',', '.') }}% a.a., retorno de {{ number_format($p['retorno_anual'], 1, ',', '.') }}% a.a., inflação de {{ number_format($p['inflacao_anual'], 1, ',', '.') }}% a.a. corrigindo o custo de vida. Horizonte de {{ $p['resumo']['horizonte_anos'] }} anos.">
            <x-slot name="afterHeader">
                <div class="vr-toggle">
                    <x-filament::button size="sm" :color="$this->tableMode === 'anual' ? 'primary' : 'gray'" wire:click="$set('tableMode', 'anual')">
                        Anual
                    </x-filament::button>
                    <x-filament::button size="sm" :color="$this->tableMode === 'mensal' ? 'primary' : 'gray'" wire:click="$set('tableMode', 'mensal')">
                        Mensal
                    </x-filament::button>
                </div>
            </x-slot>

            {{-- Resumo estilo calculadora de juros compostos: onde a bola de neve chega. --}}
            <div class="vr-resumo">
                <div class="vr-card">
                    <small>Valor total final</small>
                    <strong>{{ $moneyShort($p['resumo']['patrimonio']) }}</strong>
                </div>
                <div class="vr-card">
                    <small>Total investido (hoje + aportes)</small>
                    <strong>{{ $moneyShort($p['resumo']['total_investido']) }}</strong>
                </div>
                <div class="vr-card">
                    <small>Total em juros</small>
                    <strong class="vr-juros">{{ $moneyShort($p['resumo']['total_juros']) }}</strong>
                </div>
            </div>

            <div class="vr-wrap">
                @if ($this->tableMode === 'anual')
                    <table class="vr-table">
                        <thead>
                        <tr>
                            <th>Ano</th>
                            <th>Aporte/mês</th>
                            <th>Juros no ano</th>
                            <th>Total investido</th>
                            <th>Total em juros</th>
                            <th>Patrimônio</th>
                            <th>Renda passiva/mês</th>
                            <th>Custo de vida/mês</th>
                            <th>Cobertura</th>
                        </tr>
                        </thead>
                        <tbody>
                        @php($marcoMarcado = false)
                        @foreach ($p['table_anual'] as $row)
                            @php($ehMarco = ! $marcoMarcado && $row['cobertura_pct'] >= 100)
                            @php($marcoMarcado = $marcoMarcado || $ehMarco)
                            <tr @class(['vr-marco' => $ehMarco])>
                                <td>{{ $row['ano'] }}{{ $ehMarco ? ' 🚀' : '' }}</td>
                                <td>{{ $money($row['aporte_mensal']) }}</td>
                                <td class="vr-juros">{{ $moneyShort($row['juros_no_ano']) }}</td>
                                <td>{{ $moneyShort($row['total_investido']) }}</td>
                                <td class="vr-juros">{{ $moneyShort($row['total_juros']) }}</td>
                                <td>{{ $moneyShort($row['patrimonio']) }}</td>
                                <td>{{ $money($row['renda_mensal']) }}</td>
                                <td>{{ $money($row['custo_mensal']) }}</td>
                                <td @class(['vr-ok' => $row['cobertura_pct'] >= 100])>{{ number_format($row['cobertura_pct'], 1, ',', '.') }}%</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <table class="vr-table">
                        <thead>
                        <tr>
                            <th>Mês</th>
                            <th>Aporte</th>
                            <th>Juros no mês</th>
                            <th>Total investido</th>
                            <th>Total em juros</th>
                            <th>Patrimônio</th>
                            <th>Renda passiva/mês</th>
                            <th>Cobertura</th>
                        </tr>
                        </thead>
                        <tbody>
                        @php($marcoMarcado = false)
                        @foreach ($p['table_mensal'] as $row)
                            @php($ehMarco = ! $marcoMarcado && $row['cobertura_pct'] >= 100)
                            @php($marcoMarcado = $marcoMarcado || $ehMarco)
                            <tr @class(['vr-marco' => $ehMarco])>
                                <td>{{ $row['mes'] }}{{ $ehMarco ? ' 🚀' : '' }}</td>
                                <td>{{ $money($row['aporte']) }}</td>
                                <td class="vr-juros">{{ $money($row['juros']) }}</td>
                                <td>{{ $moneyShort($row['total_investido']) }}</td>
                                <td class="vr-juros">{{ $moneyShort($row['total_juros']) }}</td>
                                <td>{{ $moneyShort($row['patrimonio']) }}</td>
                                <td>{{ $money($row['renda_mensal']) }}</td>
                                <td @class(['vr-ok' => $row['cobertura_pct'] >= 100])>{{ number_format($row['cobertura_pct'], 1, ',', '.') }}%</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </x-filament::section>
    @endif

    <p style="font-size: .875rem; opacity: .7; max-width: 60ch;">
        A pergunta que todo investidor faz no chuveiro, respondida com os seus números:
        no ritmo atual de aportes e com o yield real da sua carteira, quando a renda
        passiva cobre o seu custo de vida? Ajuste o plano e veja a data se mover.
        A projeção é um exercício de juros compostos — não é promessa de retorno.
    </p>
</x-filament-panels::page>
