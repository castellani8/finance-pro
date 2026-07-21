<x-filament-panels::page>
    @php
        $report = $this->getReport();
        $money = fn (float $v): string => 'R$ ' . number_format($v, 2, ',', '.');
        $meses = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    @endphp

    <style>
        .ir-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .ir-table th { text-align: left; padding: .5rem .75rem; border-bottom: 2px solid rgba(128,128,128,.35); white-space: nowrap; }
        .ir-table td { padding: .5rem .75rem; border-bottom: 1px solid rgba(128,128,128,.18); vertical-align: top; }
        .ir-table .num { text-align: right; white-space: nowrap; }
        .ir-note { font-size: .8rem; opacity: .7; margin-top: .75rem; line-height: 1.5; }
        .ir-badge { display: inline-block; padding: .1rem .5rem; border-radius: 9999px; font-size: .75rem; background: rgba(239,68,68,.15); color: #ef4444; }
        .ir-disc { font-size: .8rem; opacity: .85; }
    </style>

    <x-filament::section>
        <div style="display: flex; align-items: center; gap: .75rem">
            <label for="ir-year" style="font-weight: 600">Ano-calendário:</label>
            <select id="ir-year" wire:model.live="year"
                    style="border: 1px solid rgba(128,128,128,.4); border-radius: .5rem; padding: .375rem 2rem .375rem .75rem; background: transparent">
                @foreach ($this->getYearOptions() as $option)
                    <option value="{{ $option }}">{{ $option }} (declaração {{ $option + 1 }})</option>
                @endforeach
            </select>
        </div>
    </x-filament::section>

    <x-filament::section heading="Bens e Direitos" description="Posição e custo de aquisição por preço médio em 31/12 — copie a discriminação para a ficha correspondente.">
        <div style="overflow-x: auto">
            <table class="ir-table">
                <thead>
                    <tr>
                        <th>Grupo / Código</th>
                        <th>Ativo</th>
                        <th class="num">Qtd. 31/12/{{ $report['year'] }}</th>
                        <th class="num">Situação 31/12/{{ $report['year'] - 1 }}</th>
                        <th class="num">Situação 31/12/{{ $report['year'] }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['bens'] as $bem)
                        <tr>
                            <td>{{ $bem['grupo_codigo'] }}</td>
                            <td>
                                <strong>{{ $bem['ticker'] ?? $bem['nome'] }}</strong>
                                <div class="ir-disc">{{ $bem['discriminacao'] }}</div>
                            </td>
                            <td class="num">{{ rtrim(rtrim(number_format($bem['quantidade'], 6, ',', '.'), '0'), ',') }}</td>
                            <td class="num">{{ $money($bem['custo_anterior']) }}</td>
                            <td class="num">{{ $money($bem['custo_atual']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Nenhuma posição em 31/12/{{ $report['year'] }}.</td></tr>
                    @endforelse
                </tbody>
                @if ($report['bens'] !== [])
                    <tfoot>
                        <tr style="font-weight: 700">
                            <td colspan="3">Total</td>
                            <td class="num">{{ $money($report['totais']['custo_anterior']) }}</td>
                            <td class="num">{{ $money($report['totais']['custo_atual']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-filament::section>

    <x-filament::section heading="Proventos recebidos no ano" description="Dividendos e rendimentos de FII entram em Rendimentos Isentos; JCP e juros de renda fixa em Tributação Exclusiva.">
        <div style="overflow-x: auto">
            <table class="ir-table">
                <thead>
                    <tr>
                        <th>Ativo</th>
                        <th class="num">Isentos</th>
                        <th class="num">Trib. exclusiva</th>
                        <th class="num">Outros</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['proventos'] as $provento)
                        <tr>
                            <td><strong>{{ $provento['ticker'] ?? '—' }}</strong> <span class="ir-disc">{{ \Illuminate\Support\Str::limit($provento['nome'], 45) }}</span></td>
                            <td class="num">{{ $money($provento['isentos']) }}</td>
                            <td class="num">{{ $money($provento['exclusivos']) }}</td>
                            <td class="num">{{ $money($provento['outros']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">Nenhum provento em {{ $report['year'] }}.</td></tr>
                    @endforelse
                </tbody>
                @if ($report['proventos'] !== [])
                    <tfoot>
                        <tr style="font-weight: 700">
                            <td>Total</td>
                            <td class="num">{{ $money($report['totais']['isentos']) }}</td>
                            <td class="num">{{ $money($report['totais']['exclusivos']) }}</td>
                            <td class="num">{{ $money($report['totais']['outros']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-filament::section>

    <x-filament::section heading="Vendas por mês" description="Vendas de ações até R$ 20.000,00 no mês são isentas de ganho de capital. FIIs não têm isenção.">
        <div style="overflow-x: auto">
            <table class="ir-table">
                <thead>
                    <tr>
                        <th>Mês</th>
                        <th class="num">Ações</th>
                        <th class="num">FIIs</th>
                        <th class="num">Outros / Resgates</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['vendas'] as $venda)
                        <tr>
                            <td>{{ $meses[$venda['mes']] }}</td>
                            <td class="num">{{ $money($venda['acoes']) }}</td>
                            <td class="num">{{ $money($venda['fiis']) }}</td>
                            <td class="num">{{ $money($venda['outros']) }}</td>
                            <td>@if ($venda['acoes_acima_isencao'])<span class="ir-badge">acima da isenção — apurar ganho</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Nenhuma venda em {{ $report['year'] }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <p class="ir-note">
        ⚠️ Este relatório é um apoio ao preenchimento da declaração, calculado pelo preço médio das operações importadas.
        Confira sempre com os <strong>informes de rendimentos</strong> das corretoras e das companhias. A apuração de ganho de capital
        (DARF mensal) e ajustes de bonificação com custo informado pela empresa não estão inclusos. Isto não constitui aconselhamento tributário.
    </p>
</x-filament-panels::page>
