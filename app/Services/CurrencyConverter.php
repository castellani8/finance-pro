<?php

namespace App\Services;

use App\Models\CurrencyRate;

/**
 * Converte valores em moeda estrangeira para BRL usando a última cotação
 * conhecida até a data (PTAX venda). Sem cotação disponível, usa a primeira
 * da série (datas anteriores ao histórico) ou 1:1 como último recurso.
 */
class CurrencyConverter
{
    public const SYMBOLS = [
        'BRL' => 'R$',
        'USD' => 'US$',
        'EUR' => '€',
    ];

    /** @var array<string, array{dates: array<int, string>, rates: array<int, float>}|null> */
    private static array $cache = [];

    public static function symbol(?string $currency): string
    {
        return self::SYMBOLS[strtoupper((string) $currency)] ?? 'R$';
    }

    /** Limpa o cache estático (necessário em testes com RefreshDatabase). */
    public static function flush(): void
    {
        self::$cache = [];
    }

    public function toBrl(float $amount, ?string $currency, ?string $date = null): float
    {
        return $amount * $this->rate($currency, $date);
    }

    /** Cotação BRL por unidade da moeda na data (1.0 para BRL/desconhecida). */
    public function rate(?string $currency, ?string $date = null): float
    {
        $currency = strtoupper((string) $currency);

        if ($currency === '' || $currency === 'BRL') {
            return 1.0;
        }

        $series = $this->series($currency);

        if ($series === null) {
            return 1.0;
        }

        $date ??= now()->toDateString();
        $dates = $series['dates'];
        $rates = $series['rates'];

        $lo = 0;
        $hi = count($dates) - 1;
        $pos = -1;

        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);

            if ($dates[$mid] <= $date) {
                $pos = $mid;
                $lo = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }

        // Antes do início da série, usa a primeira cotação conhecida.
        return $pos >= 0 ? $rates[$pos] : $rates[0];
    }

    /** @return array{dates: array<int, string>, rates: array<int, float>}|null */
    private function series(string $currency): ?array
    {
        if (array_key_exists($currency, self::$cache)) {
            return self::$cache[$currency];
        }

        $rows = CurrencyRate::query()
            ->where('currency', $currency)
            ->orderBy('date')
            ->get(['date', 'rate']);

        if ($rows->isEmpty()) {
            return self::$cache[$currency] = null;
        }

        return self::$cache[$currency] = [
            'dates' => $rows->map(fn (CurrencyRate $r): string => substr((string) $r->getRawOriginal('date'), 0, 10))->all(),
            'rates' => $rows->map(fn (CurrencyRate $r): float => (float) $r->rate)->all(),
        ];
    }
}
