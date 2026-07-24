<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Carta de Patrimônio — {{ $tenant->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; }
        body { font-family: Georgia, 'Times New Roman', serif; color: #1a1a1a; background: #f6f4ee; padding: 2rem 1rem; font-size: 14px; }
        .wrap { max-width: 860px; margin: 0 auto; background: #fff; padding: 3rem; border-top: 6px solid #D4AF37; }
        .brand { font-family: Arial, sans-serif; color: #B18F27; font-weight: 700; letter-spacing: 3px; font-size: .7rem; }
        h1 { font-size: 1.6rem; margin: .5rem 0 .25rem; }
        .sub { color: #666; font-size: .85rem; margin-bottom: 1.5rem; }
        .aviso { background: #f9f5e8; border-left: 4px solid #D4AF37; padding: .9rem 1rem; font-size: .85rem; line-height: 1.55; margin-bottom: 1.75rem; }
        h2 { font-family: Arial, sans-serif; font-size: .8rem; letter-spacing: 2px; text-transform: uppercase; color: #8C6F1D; margin: 1.75rem 0 .5rem; }
        table { width: 100%; border-collapse: collapse; font-size: .82rem; font-family: Arial, sans-serif; }
        th { text-align: left; padding: .4rem .5rem; border-bottom: 2px solid #D4AF37; font-size: .72rem; text-transform: uppercase; color: #777; }
        td { padding: .45rem .5rem; border-bottom: 1px solid #eee; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        footer { margin-top: 2.5rem; font-size: .78rem; color: #777; line-height: 1.6; border-top: 1px solid #ddd; padding-top: 1rem; }
        .print-btn { position: fixed; right: 1rem; bottom: 1rem; background: #D4AF37; color: #0A0A0A; border: 0; border-radius: 999px; padding: .7rem 1.3rem; font-weight: 700; font-family: Arial, sans-serif; cursor: pointer; box-shadow: 0 6px 20px rgba(0,0,0,.25); }
        @media print { body { background: #fff; padding: 0; } .wrap { padding: 1rem; } .no-print { display: none; } }
    </style>
</head>
<body>
@php($money = fn (float $v): string => 'R$ '.number_format($v, 2, ',', '.'))

<div class="wrap">
    <span class="brand">MILIA INVEST</span>
    <h1>Carta de Patrimônio</h1>
    <p class="sub">Carteira "{{ $tenant->name }}" · gerada em {{ now()->format('d/m/Y') }} · documento confidencial</p>

    <div class="aviso">
        <strong>Para a minha família:</strong> este documento existe para que, se algo acontecer
        comigo, vocês saibam exatamente onde está cada parte do nosso patrimônio e a quem
        recorrer. Guardem junto dos documentos importantes e procurem um advogado de confiança
        para orientar o inventário.
    </div>

    <h2>Pessoas com acesso à carteira</h2>
    <table>
        <thead><tr><th>Nome</th><th>E-mail</th><th>Telefone</th></tr></thead>
        <tbody>
        @foreach ($members as $member)
            <tr>
                <td>{{ $member->name }}</td>
                <td>{{ $member->email }}</td>
                <td>{{ $member->phone ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h2>Contas e saldos</h2>
    <table>
        <thead><tr><th>Conta</th><th>Tipo</th><th>Moeda</th><th class="num">Saldo (em R$)</th></tr></thead>
        <tbody>
        @forelse ($accounts as $account)
            <tr>
                <td>{{ $account->name }}</td>
                <td>{{ ['bank' => 'Banco', 'broker' => 'Corretora', 'cash' => 'Dinheiro'][$account->kind] ?? $account->kind }}</td>
                <td>{{ $account->currency ?? 'BRL' }}</td>
                <td class="num">{{ $money($account->balanceInBrlAt()) }}</td>
            </tr>
        @empty
            <tr><td colspan="4">Nenhuma conta cadastrada.</td></tr>
        @endforelse
        </tbody>
    </table>

    @foreach ($assetsByType as $tipo => $assets)
        <h2>{{ $tipo }}</h2>
        <table>
            <thead><tr><th>Ativo</th><th>Ticker/Código</th><th>Instituição / custódia</th><th class="num">Quantidade</th><th class="num">Valor atual (R$)</th></tr></thead>
            <tbody>
            @foreach ($assets as $asset)
                <tr>
                    <td>{{ $asset->name }}</td>
                    <td>{{ $asset->ticker_or_code ?: '—' }}</td>
                    <td>{{ $asset->currentInstitution() ?? '—' }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($asset->positionQuantity(), 6, ',', '.'), '0'), ',') }}</td>
                    <td class="num">{{ $money($asset->currentValue()) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endforeach

    @if ($companies->isNotEmpty())
        <h2>Empresas</h2>
        <table>
            <thead><tr><th>Empresa</th></tr></thead>
            <tbody>
            @foreach ($companies as $company)
                <tr><td>{{ $company->name }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <footer>
        Valores na data de geração — cotações e saldos mudam todos os dias; o que importa aqui é
        <em>onde</em> cada coisa está. Uma versão sempre atualizada vive no painel do Milia Invest
        ({{ url('/app') }}), acessível por qualquer pessoa listada acima.
        Recomendamos reimprimir esta carta a cada 6 meses.
    </footer>
</div>

<button class="print-btn no-print" onclick="window.print()">Imprimir / salvar PDF</button>
</body>
</html>
