<?php

namespace App\Ai;

/**
 * Renderiza os gráficos da Milha como SVG puro (sem JS), no padrão preto &
 * ouro. Texto usa currentColor para acompanhar o tema claro/escuro do chat.
 * A spec já chega validada pela tool GerarGrafico.
 */
class ChartSvg
{
    private const COLORS = ['#D4AF37', '#7C9CBF', '#A8862A', '#8FBF9F', '#E8CD6F', '#6B7280'];

    private const W = 340;

    /** @param array{tipo: string, titulo: string, labels: array<int, string>, series: array<int, array{nome: string, valores: array<int, float>}>, moeda: bool} $spec */
    public static function render(array $spec): string
    {
        return match ($spec['tipo']) {
            'pizza' => self::pie($spec),
            'linha' => self::cartesian($spec, bars: false),
            default => self::cartesian($spec, bars: true),
        };
    }

    /** Barras (agrupadas por série) e linhas compartilham eixos e legenda. */
    private static function cartesian(array $spec, bool $bars): string
    {
        $labels = $spec['labels'];
        $series = $spec['series'];
        $n = count($labels);

        $all = array_merge(...array_map(fn ($s) => $s['valores'], $series));
        $max = max(max($all), 0.0);
        $min = min(min($all), 0.0);

        if ($max === $min) {
            $max = $min + 1;
        }

        $titleH = 22;
        $plotTop = $titleH + 8;
        $plotH = 150;
        $plotLeft = 44;
        $plotRight = self::W - 8;
        $plotW = $plotRight - $plotLeft;
        $xLabelH = 18;
        $legendH = count($series) > 1 ? 18 : 0;
        $height = $plotTop + $plotH + $xLabelH + $legendH + 6;

        $y = fn (float $v): float => $plotTop + $plotH - ($v - $min) / ($max - $min) * $plotH;

        $svg = self::open($height, $spec['titulo']);

        // Linhas de grade + rótulos do eixo Y (mín, zero se houver, máx).
        $gridValues = array_unique([$min, ($min < 0 && $max > 0) ? 0.0 : ($min + $max) / 2, $max]);

        foreach ($gridValues as $gv) {
            $gy = $y($gv);
            $svg .= '<line x1="'.$plotLeft.'" y1="'.self::n($gy).'" x2="'.$plotRight.'" y2="'.self::n($gy).'" stroke="currentColor" stroke-opacity=".15"/>';
            $svg .= '<text x="'.($plotLeft - 4).'" y="'.self::n($gy + 3).'" font-size="8" text-anchor="end" fill="currentColor" fill-opacity=".6">'.self::fmt($gv, $spec['moeda']).'</text>';
        }

        // Rótulos do eixo X (no máximo 8, truncados).
        $step = (int) ceil($n / 8);
        $slot = $plotW / max(1, $n);

        foreach ($labels as $i => $label) {
            if ($i % $step !== 0) {
                continue;
            }

            $cx = $plotLeft + $slot * ($i + 0.5);
            $svg .= '<text x="'.self::n($cx).'" y="'.($plotTop + $plotH + 12).'" font-size="8" text-anchor="middle" fill="currentColor" fill-opacity=".6">'
                .e(mb_strimwidth($label, 0, 8, '…')).'</text>';
        }

        if ($bars) {
            $groupW = $slot * 0.68;
            $barW = $groupW / count($series);
            $zeroY = $y(max($min, min(0.0, $max)));

            foreach ($series as $si => $serie) {
                foreach ($serie['valores'] as $i => $v) {
                    $vy = $y($v);
                    $x = $plotLeft + $slot * ($i + 0.5) - $groupW / 2 + $barW * $si;
                    $svg .= '<rect x="'.self::n($x).'" y="'.self::n(min($vy, $zeroY)).'" width="'.self::n(max(1, $barW - 1)).'" height="'.self::n(max(1, abs($zeroY - $vy))).'" rx="1.5" fill="'.self::COLORS[$si % count(self::COLORS)].'"/>';

                    // Valor em cima da barra só quando o gráfico é pequeno.
                    if ($n * count($series) <= 8) {
                        $svg .= '<text x="'.self::n($x + $barW / 2).'" y="'.self::n(min($vy, $zeroY) - 3).'" font-size="8" text-anchor="middle" fill="currentColor" fill-opacity=".75">'.self::fmt($v, $spec['moeda']).'</text>';
                    }
                }
            }
        } else {
            foreach ($series as $si => $serie) {
                $color = self::COLORS[$si % count(self::COLORS)];
                $points = [];

                foreach ($serie['valores'] as $i => $v) {
                    $points[] = self::n($plotLeft + $slot * ($i + 0.5)).','.self::n($y($v));
                }

                $svg .= '<polyline points="'.implode(' ', $points).'" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>';

                if ($n <= 16) {
                    foreach ($serie['valores'] as $i => $v) {
                        $svg .= '<circle cx="'.self::n($plotLeft + $slot * ($i + 0.5)).'" cy="'.self::n($y($v)).'" r="2.4" fill="'.$color.'"/>';
                    }
                }
            }
        }

        if (count($series) > 1) {
            $svg .= self::legend($series, $plotTop + $plotH + $xLabelH + 4);
        }

        return $svg.'</svg>';
    }

    private static function pie(array $spec): string
    {
        $labels = $spec['labels'];
        $valores = $spec['series'][0]['valores'];
        $total = array_sum($valores);

        $titleH = 22;
        $r = 62;
        $cx = 86;
        $cy = $titleH + 10 + $r;
        $legendX = 170;
        $height = max($cy + $r + 12, $titleH + 14 + count($labels) * 17);

        $svg = self::open($height, $spec['titulo']);

        $angle = -M_PI / 2;

        foreach ($valores as $i => $v) {
            $frac = $total > 0 ? $v / $total : 0;
            $end = $angle + $frac * 2 * M_PI;
            $color = self::COLORS[$i % count(self::COLORS)];

            // Fatia única (100%) vira círculo; arco não fecharia.
            if ($frac >= 0.999) {
                $svg .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="'.$color.'"/>';
            } elseif ($frac > 0) {
                $x1 = $cx + $r * cos($angle);
                $y1 = $cy + $r * sin($angle);
                $x2 = $cx + $r * cos($end);
                $y2 = $cy + $r * sin($end);
                $large = $frac > 0.5 ? 1 : 0;
                $svg .= '<path d="M'.self::n($cx).' '.self::n($cy).' L'.self::n($x1).' '.self::n($y1)
                    .' A'.$r.' '.$r.' 0 '.$large.' 1 '.self::n($x2).' '.self::n($y2).' Z" fill="'.$color.'" stroke="rgba(0,0,0,.25)" stroke-width=".5"/>';
            }

            $angle = $end;

            $ly = $titleH + 14 + $i * 17;
            $pct = $total > 0 ? round($v / $total * 100) : 0;
            $svg .= '<circle cx="'.($legendX + 4).'" cy="'.($ly - 3).'" r="4" fill="'.$color.'"/>';
            $svg .= '<text x="'.($legendX + 13).'" y="'.$ly.'" font-size="9" fill="currentColor">'
                .e(mb_strimwidth($labels[$i], 0, 16, '…')).'</text>';
            $svg .= '<text x="'.($legendX + 13).'" y="'.($ly + 8).'" font-size="8" fill="currentColor" fill-opacity=".6">'
                .self::fmt($v, $spec['moeda']).' · '.$pct.'%</text>';
        }

        // Furo do donut por cima das fatias.
        $svg .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.($r * 0.55).'" fill="var(--milha-chart-bg, transparent)" class="milha-donut-hole"/>';

        return $svg.'</svg>';
    }

    /** @param array<int, array{nome: string, valores: array<int, float>}> $series */
    private static function legend(array $series, float $y): string
    {
        $svg = '';
        $x = 44;

        foreach ($series as $si => $serie) {
            $color = self::COLORS[$si % count(self::COLORS)];
            $nome = mb_strimwidth($serie['nome'], 0, 14, '…');
            $svg .= '<circle cx="'.self::n($x + 4).'" cy="'.self::n($y + 4).'" r="4" fill="'.$color.'"/>';
            $svg .= '<text x="'.self::n($x + 12).'" y="'.self::n($y + 7).'" font-size="9" fill="currentColor">'.e($nome).'</text>';
            $x += 12 + mb_strlen($nome) * 5.4 + 16;
        }

        return $svg;
    }

    private static function open(float $height, string $titulo): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.self::W.' '.self::n($height).'" role="img" style="width:100%;height:auto;display:block">'
            .'<text x="'.(self::W / 2).'" y="14" font-size="11" font-weight="700" text-anchor="middle" fill="currentColor">'
            .e(mb_strimwidth($titulo, 0, 48, '…')).'</text>';
    }

    /** Números curtos em pt-BR: 1.234,56 → "1,2 mil"; opcionalmente com R$. */
    private static function fmt(float $v, bool $moeda): string
    {
        $abs = abs($v);

        $texto = match (true) {
            $abs >= 1_000_000 => number_format($v / 1_000_000, 1, ',', '.').' mi',
            $abs >= 10_000 => number_format($v / 1_000, 0, ',', '.').' mil',
            $abs >= 1_000 => number_format($v / 1_000, 1, ',', '.').' mil',
            $abs >= 100 => number_format($v, 0, ',', '.'),
            default => rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ','),
        };

        return ($moeda ? 'R$ ' : '').$texto;
    }

    private static function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}
