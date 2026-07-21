<?php

namespace App\Services;

use App\Models\MarketingIndexSeries;
use Illuminate\Support\Carbon;

/**
 * Acumula o fator de rentabilidade de um título de renda fixa a partir das séries
 * econômicas (CDI, IPCA, SELIC) armazenadas em marketing_index_series.
 *
 * Suporta:
 *  - percentual do índice (ex: 110% do CDI);
 *  - spread anual sobre o índice (ex: IPCA + 5%);
 *  - prefixado (indexador PREFIXADO usa apenas o spread como taxa anual).
 *
 * Os valores em `daily_factor` são taxas em percentual (ex: 0.052531 = 0,052531% no dia).
 */
class IndexAccumulator
{
    /**
     * Cache por request das séries acumuladas, com chave "index_code|percent".
     *
     * @var array<string, array{dates: array<int, string>, cum: array<int, float>, latest: float, lastDate: string}|null>
     */
    private static array $cache = [];

    /**
     * Fator de correção total entre $fromDate (exclusivo) e a última data disponível,
     * combinando o índice (a `$indexPercent`%) com o spread anual.
     */
    public function factorSince(
        string $indexCode,
        string $fromDate,
        float $indexPercent = 100.0,
        float $spreadAnnual = 0.0,
    ): float {
        return $this->factorBetween($indexCode, $fromDate, now()->toDateString(), $indexPercent, $spreadAnnual);
    }

    /**
     * Fator de correção entre $fromDate (exclusivo) e $toDate (inclusivo),
     * usando os registros da série disponíveis dentro do intervalo.
     */
    public function factorBetween(
        string $indexCode,
        string $fromDate,
        string $toDate,
        float $indexPercent = 100.0,
        float $spreadAnnual = 0.0,
    ): float {
        $indexCode = strtoupper($indexCode);

        if ($indexCode === 'PREFIXADO' || $indexCode === '') {
            return $this->spreadFactor($spreadAnnual, $fromDate, $toDate);
        }

        $series = $this->series($indexCode, $indexPercent);

        if ($series === null || $series['latest'] <= 0) {
            return $this->spreadFactor($spreadAnnual, $fromDate, $toDate);
        }

        $base = $this->cumulativeAt($series, $fromDate);
        $target = $this->cumulativeAt($series, $toDate);
        $indexFactor = $base > 0 ? $target / $base : 1.0;

        // O spread capitaliza até onde a série alcança (não projeta o futuro).
        $spreadUntil = min($toDate, $series['lastDate']);

        return $indexFactor * $this->spreadFactor($spreadAnnual, $fromDate, $spreadUntil);
    }

    /**
     * Fator acumulado da série no último registro com data <= $date (1.0 antes do início).
     *
     * @param  array{dates: array<int, string>, cum: array<int, float>, latest: float, lastDate: string}  $series
     */
    private function cumulativeAt(array $series, string $date): float
    {
        $dates = $series['dates'];
        $cum = $series['cum'];

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

        return $pos >= 0 ? $cum[$pos] : 1.0;
    }

    /** Fator do spread anual capitalizado no período (ex: +5% a.a.). */
    private function spreadFactor(float $spreadAnnual, string $fromDate, string $toDate): float
    {
        if ($spreadAnnual == 0.0) {
            return 1.0;
        }

        $years = Carbon::parse($fromDate)->floatDiffInYears(Carbon::parse($toDate));

        if ($years <= 0) {
            return 1.0;
        }

        return (1 + $spreadAnnual / 100) ** $years;
    }

    /**
     * @return array{dates: array<int, string>, cum: array<int, float>, latest: float, lastDate: string}|null
     */
    private function series(string $indexCode, float $indexPercent): ?array
    {
        $key = $indexCode.'|'.$indexPercent;

        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $rows = MarketingIndexSeries::query()
            ->where('index_code', $indexCode)
            ->orderBy('date')
            ->get(['date', 'daily_factor']);

        if ($rows->isEmpty()) {
            return self::$cache[$key] = null;
        }

        $dates = [];
        $cum = [];
        $running = 1.0;

        foreach ($rows as $row) {
            $running *= 1 + ((float) $row->daily_factor * $indexPercent / 100) / 100;
            $dates[] = (string) $row->date;
            $cum[] = $running;
        }

        return self::$cache[$key] = [
            'dates' => $dates,
            'cum' => $cum,
            'latest' => $running,
            'lastDate' => end($dates) ?: now()->toDateString(),
        ];
    }
}
