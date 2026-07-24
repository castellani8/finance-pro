<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Relatório IR {{ $report['year'] }} — {{ $tenant->name }} · Milia Invest</title>
    <style>
        * { box-sizing: border-box; margin: 0; }
        body { font-family: -apple-system, 'Segoe UI', Arial, sans-serif; color: #1a1a1a; background: #f6f4ee; padding: 2rem 1rem; font-size: 14px; }
        .wrap { max-width: 960px; margin: 0 auto; }
        header { background: #0A0A0A; color: #fff; border-radius: 12px 12px 0 0; padding: 1.5rem 2rem; border-bottom: 3px solid #D4AF37; }
        header h1 { font-size: 1.3rem; }
        header p { color: #bbb; font-size: .85rem; margin-top: .25rem; }
        header .brand { color: #D4AF37; font-weight: 700; letter-spacing: 2px; font-size: .75rem; }
        main { background: #fff; border-radius: 0 0 12px 12px; padding: 2rem; }
        h2 { font-size: 1rem; margin: 1.75rem 0 .5rem; border-bottom: 2px solid #D4AF37; padding-bottom: .3rem; }
        h2:first-child { margin-top: 0; }
        table { width: 100%; border-collapse: collapse; font-size: .8rem; }
        th { text-align: left; background: #f3efe3; padding: .45rem .5rem; white-space: nowrap; }
        td { padding: .4rem .5rem; border-bottom: 1px solid #eee; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .disc { color: #555; font-size: .72rem; max-width: 34ch; }
        .totais { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: .75rem; }
        .totais div { background: #f3efe3; border-radius: 8px; padding: .6rem .9rem; }
        .totais small { display: block; color: #777; font-size: .7rem; }
        footer { text-align: center; color: #888; font-size: .75rem; padding: 1.25rem; }
        @media print {
            body { background: #fff; padding: 0; }
            header { border-radius: 0; }
            .no-print { display: none; }
        }
        .print-btn { position: fixed; right: 1rem; bottom: 1rem; background: #D4AF37; color: #0A0A0A; border: 0; border-radius: 999px; padding: .7rem 1.3rem; font-weight: 700; cursor: pointer; box-shadow: 0 6px 20px rgba(0,0,0,.25); }
    </style>
</head>
<body>
@php($n = fn (float $v): string => number_format($v, 2, ',', '.'))
@php($meses = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'])

<div class="wrap">
    <header>
        <span class="brand">MILIA INVEST</span>
        <h1>Relatório para Imposto de Renda — ano-base {{ $report['year'] }}</h1>
        <p>Carteira: {{ $tenant->name }} · Documento somente leitura, gerado para o contador · Link expira automaticamente</p>
    </header>

    <main>
        <h2>Bens e direitos</h2>
        <table>
            <thead>
            <tr>
                <th>Grupo/Código</th><th>Ticker</th><th>Nome</th>
                <th class="num">Quantidade</th>
                <th class="num">Situação 31/12/{{ $report['year'] - 1 }}</th>
                <th class="num">Situação 31/12/{{ $report['year'] }}</th>
                <th>Discriminação sugerida</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($report['bens'] as $b)
                <tr>
                    <td>{{ $b['grupo_codigo'] }}</td>
                    <td>{{ $b['ticker'] ?: '—' }}</td>
                    <td>{{ $b['nome'] }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($b['quantidade'], 6, ',', '.'), '0'), ',') }}</td>
                    <td class="num">R$ {{ $n($b['custo_anterior']) }}</td>
                    <td class="num">R$ {{ $n($b['custo_atual']) }}</td>
                    <td class="disc">{{ $b['discriminacao'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Sem bens no período.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="totais">
            <div><small>Total 31/12/{{ $report['year'] - 1 }}</small><strong>R$ {{ $n($report['totais']['custo_anterior']) }}</strong></div>
            <div><small>Total 31/12/{{ $report['year'] }}</small><strong>R$ {{ $n($report['totais']['custo_atual']) }}</strong></div>
        </div>

        <h2>Proventos recebidos</h2>
        <table>
            <thead>
            <tr>
                <th>Ticker</th><th>Nome</th>
                <th class="num">Isentos (dividendos/rendimentos)</th>
                <th class="num">Tributação exclusiva (JCP/juros)</th>
                <th class="num">Outros</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($report['proventos'] as $p)
                <tr>
                    <td>{{ $p['ticker'] ?: '—' }}</td>
                    <td>{{ $p['nome'] }}</td>
                    <td class="num">R$ {{ $n($p['isentos']) }}</td>
                    <td class="num">R$ {{ $n($p['exclusivos']) }}</td>
                    <td class="num">R$ {{ $n($p['outros']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Sem proventos no ano.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="totais">
            <div><small>Isentos</small><strong>R$ {{ $n($report['totais']['isentos']) }}</strong></div>
            <div><small>Tributação exclusiva</small><strong>R$ {{ $n($report['totais']['exclusivos']) }}</strong></div>
            <div><small>Outros</small><strong>R$ {{ $n($report['totais']['outros']) }}</strong></div>
        </div>

        <h2>Vendas por mês</h2>
        <table>
            <thead>
            <tr>
                <th>Mês</th>
                <th class="num">Ações</th><th class="num">FIIs</th><th class="num">Opções</th><th class="num">Outros</th>
                <th>Ações acima da isenção (R$ 20 mil)</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($report['vendas'] as $v)
                <tr>
                    <td>{{ $meses[$v['mes']] }}</td>
                    <td class="num">R$ {{ $n($v['acoes']) }}</td>
                    <td class="num">R$ {{ $n($v['fiis']) }}</td>
                    <td class="num">R$ {{ $n($v['opcoes'] ?? 0) }}</td>
                    <td class="num">R$ {{ $n($v['outros']) }}</td>
                    <td>{{ $v['acoes_acima_isencao'] ? 'SIM' : 'não' }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Sem vendas no ano.</td></tr>
            @endforelse
            </tbody>
        </table>

        <h2>Ganho de capital e DARF</h2>
        <table>
            <thead>
            <tr>
                <th>Mês</th>
                <th class="num">Ganho ações</th><th>Isento?</th><th class="num">DARF ações</th>
                <th class="num">Ganho FIIs</th><th class="num">DARF FIIs</th>
                <th class="num">DARF total</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($report['ganhos'] as $g)
                <tr>
                    <td>{{ $meses[$g['mes']] }}</td>
                    <td class="num">R$ {{ $n($g['acoes']['ganho']) }}</td>
                    <td>{{ $g['acoes']['isento'] ? 'sim' : 'não' }}</td>
                    <td class="num">R$ {{ $n($g['acoes']['darf']) }}</td>
                    <td class="num">R$ {{ $n($g['fiis']['ganho']) }}</td>
                    <td class="num">R$ {{ $n($g['fiis']['darf']) }}</td>
                    <td class="num"><strong>R$ {{ $n($g['darf']) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="7">Sem apuração de ganho de capital no ano.</td></tr>
            @endforelse
            </tbody>
        </table>
        @if (($report['totais']['darf'] ?? 0) > 0)
            <div class="totais">
                <div><small>DARF total no ano</small><strong>R$ {{ $n($report['totais']['darf']) }}</strong></div>
            </div>
        @endif
    </main>

    <footer>
        Gerado pelo Milia Invest em {{ now()->format('d/m/Y') }} a pedido do titular da carteira.
        Os valores têm caráter informativo e devem ser conferidos antes da declaração.
    </footer>
</div>

<button class="print-btn no-print" onclick="window.print()">Imprimir / salvar PDF</button>
</body>
</html>
