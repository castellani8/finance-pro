<x-filament-widgets::widget>
    <x-filament::section heading="Fluxo de caixa — os números"
        description="A mesma base do gráfico acima, mês a mês, para conferência.">
        <x-slot name="afterHeader">
            <select wire:model.live="months"
                    style="font-size:.8rem; border-radius:.5rem; border:1px solid rgba(128,128,128,.35); background:transparent; padding:.3rem 1.75rem .3rem .6rem;">
                <option value="12">Últimos 12 meses</option>
                <option value="24">Últimos 2 anos</option>
                <option value="36">Últimos 3 anos</option>
            </select>
        </x-slot>

        <style>
            .cf-wrap { overflow-x: auto; }
            .cf-table { width: 100%; border-collapse: collapse; font-size: .8rem; }
            .cf-table th { text-align: right; padding: .5rem .6rem; font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; opacity: .55; border-bottom: 1px solid rgba(128,128,128,.3); }
            .cf-table th:first-child { text-align: left; }
            .cf-table td { text-align: right; padding: .45rem .6rem; border-bottom: 1px solid rgba(128,128,128,.12); white-space: nowrap; }
            .cf-table td:first-child { text-align: left; font-weight: 600; text-transform: capitalize; }
            .cf-pos { color: #16a34a; }
            .cf-neg { color: #ef4444; }
            .cf-total td { font-weight: 700; border-top: 2px solid rgba(128,128,128,.35); }
        </style>

        @php($money = fn (float $v): string => 'R$ '.number_format($v, 2, ',', '.'))

        <div class="cf-wrap">
            <table class="cf-table">
                <thead>
                <tr>
                    <th>Mês</th>
                    <th>Receitas</th>
                    <th>Despesas</th>
                    <th>Resultado</th>
                    <th>Acumulado no período</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['mes'] }}</td>
                        <td class="cf-pos">{{ $money($row['receitas']) }}</td>
                        <td class="cf-neg">−{{ $money($row['despesas']) }}</td>
                        <td @class(['cf-pos' => $row['resultado'] >= 0, 'cf-neg' => $row['resultado'] < 0])>
                            {{ ($row['resultado'] < 0 ? '−' : '+').$money(abs($row['resultado'])) }}
                        </td>
                        <td @class(['cf-pos' => $row['acumulado'] >= 0, 'cf-neg' => $row['acumulado'] < 0])>
                            {{ ($row['acumulado'] < 0 ? '−' : '+').$money(abs($row['acumulado'])) }}
                        </td>
                    </tr>
                @endforeach
                <tr class="cf-total">
                    <td>Total do período</td>
                    <td class="cf-pos">{{ $money($totals['receitas']) }}</td>
                    <td class="cf-neg">−{{ $money($totals['despesas']) }}</td>
                    <td @class(['cf-pos' => $totals['resultado'] >= 0, 'cf-neg' => $totals['resultado'] < 0])>
                        {{ ($totals['resultado'] < 0 ? '−' : '+').$money(abs($totals['resultado'])) }}
                    </td>
                    <td></td>
                </tr>
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
