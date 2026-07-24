<x-filament-panels::page>
    @php($r = $this->getReview())
    @php($money = fn (float $v): string => 'R$ '.number_format($v, 2, ',', '.'))
    @php($moneyShort = fn (float $v): string => 'R$ '.number_format($v, $v >= 1000 ? 0 : 2, ',', '.'))

    <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
        <label style="display:flex; align-items:center; gap:.5rem; font-size:.875rem;">
            Ano:
            <select wire:model.live="year" style="border-radius:.5rem; border:1px solid rgba(128,128,128,.35); background:transparent; padding:.35rem 2rem .35rem .75rem;">
                @foreach ($this->getYearOptions() as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
        </label>
        <x-filament::button color="primary" icon="heroicon-o-arrow-down-tray" x-on:click="baixarCard()">
            Baixar imagem para compartilhar
        </x-filament::button>
    </div>

    <div style="display:flex; justify-content:center;">
        {{-- Card 1080x1350 (feed) — SVG para o download em PNG ser fiel. --}}
        <svg id="retro-card" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1080 1350"
             style="width:100%; max-width:480px; border-radius:1rem; box-shadow:0 20px 60px rgba(0,0,0,.35);">
            <defs>
                <linearGradient id="gold" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#F0D882"/>
                    <stop offset="1" stop-color="#D4AF37"/>
                </linearGradient>
            </defs>

            <rect width="1080" height="1350" fill="#0A0A0A"/>
            <circle cx="1080" cy="0" r="420" fill="#D4AF37" opacity="0.08"/>
            <circle cx="0" cy="1350" r="360" fill="#D4AF37" opacity="0.06"/>
            <rect x="40" y="40" width="1000" height="1270" rx="32" fill="none" stroke="#D4AF37" stroke-opacity="0.35" stroke-width="2"/>

            <text x="90" y="150" font-family="Arial, sans-serif" font-size="34" font-weight="700" fill="#D4AF37" letter-spacing="6">MILIA INVEST</text>
            <text x="90" y="255" font-family="Arial, sans-serif" font-size="88" font-weight="800" fill="#FFFFFF">Retrospectiva</text>
            <text x="90" y="360" font-family="Arial, sans-serif" font-size="120" font-weight="800" fill="url(#gold)">{{ $r['ano'] }}</text>

            <text x="90" y="475" font-family="Arial, sans-serif" font-size="30" fill="#9CA3AF" letter-spacing="2">RENDA PASSIVA NO ANO</text>
            <text x="90" y="560" font-family="Arial, sans-serif" font-size="72" font-weight="800" fill="url(#gold)">{{ $moneyShort($r['renda_passiva_total']) }}</text>
            @if ($r['melhor_mes'])
                <text x="90" y="615" font-family="Arial, sans-serif" font-size="30" fill="#E5E7EB">Melhor mês: {{ ucfirst($r['melhor_mes']['label']) }} ({{ $moneyShort($r['melhor_mes']['valor']) }})</text>
            @endif

            <line x1="90" y1="670" x2="990" y2="670" stroke="#D4AF37" stroke-opacity="0.25" stroke-width="2"/>

            <text x="90" y="745" font-family="Arial, sans-serif" font-size="30" fill="#9CA3AF" letter-spacing="2">APORTES NO ANO</text>
            <text x="90" y="810" font-family="Arial, sans-serif" font-size="56" font-weight="800" fill="#FFFFFF">{{ $moneyShort($r['aportes']) }}</text>

            <text x="560" y="745" font-family="Arial, sans-serif" font-size="30" fill="#9CA3AF" letter-spacing="2">PATRIMÔNIO</text>
            <text x="560" y="810" font-family="Arial, sans-serif" font-size="56" font-weight="800"
                  fill="{{ ($r['variacao_pct'] ?? 0) >= 0 ? '#22C55E' : '#EF4444' }}">
                {{ $r['variacao_pct'] !== null ? (($r['variacao_pct'] >= 0 ? '+' : '').number_format($r['variacao_pct'], 1, ',', '.').'%') : '—' }}
            </text>

            <text x="90" y="905" font-family="Arial, sans-serif" font-size="30" fill="#9CA3AF" letter-spacing="2">MOVIMENTAÇÕES</text>
            <text x="90" y="965" font-family="Arial, sans-serif" font-size="52" font-weight="800" fill="#FFFFFF">{{ $r['movimentacoes'] }}</text>

            <text x="560" y="905" font-family="Arial, sans-serif" font-size="30" fill="#9CA3AF" letter-spacing="2">ATIVOS NA CARTEIRA</text>
            <text x="560" y="965" font-family="Arial, sans-serif" font-size="52" font-weight="800" fill="#FFFFFF">{{ $r['num_ativos'] }}</text>

            @if ($r['top_ativos'] !== [])
                <text x="90" y="1060" font-family="Arial, sans-serif" font-size="30" fill="#9CA3AF" letter-spacing="2">MAIORES PAGADORES</text>
                @foreach ($r['top_ativos'] as $i => $ativo)
                    <text x="90" y="{{ 1120 + $i * 55 }}" font-family="Arial, sans-serif" font-size="36" font-weight="700" fill="#E5E7EB">
                        {{ $i + 1 }}. {{ \Illuminate\Support\Str::limit($ativo['nome'], 30) }}
                    </text>
                    <text x="990" y="{{ 1120 + $i * 55 }}" text-anchor="end" font-family="Arial, sans-serif" font-size="36" font-weight="700" fill="#D4AF37">
                        {{ $moneyShort($ativo['valor']) }}
                    </text>
                @endforeach
            @endif

            <text x="540" y="1275" text-anchor="middle" font-family="Arial, sans-serif" font-size="26" fill="#6B7280">miliainvest.com — todo o seu patrimônio, uma única visão</text>
        </svg>
    </div>

    <p style="font-size:.8rem; opacity:.55; text-align:center;">
        O card não expõe seu patrimônio em valor absoluto — só renda passiva, aportes e variação percentual.
    </p>

    <script>
        function baixarCard() {
            const svg = document.getElementById('retro-card');
            const xml = new XMLSerializer().serializeToString(svg);
            const blob = new Blob([xml], { type: 'image/svg+xml;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const img = new Image();

            img.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = 1080;
                canvas.height = 1350;
                canvas.getContext('2d').drawImage(img, 0, 0, 1080, 1350);
                URL.revokeObjectURL(url);

                const a = document.createElement('a');
                a.download = 'retrospectiva-{{ $r['ano'] }}-milia-invest.png';
                a.href = canvas.toDataURL('image/png');
                a.click();
            };

            img.src = url;
        }
    </script>
</x-filament-panels::page>
