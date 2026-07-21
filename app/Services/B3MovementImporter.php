<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Observers\TransactionObserver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Importa o relatório de "Movimentação" da B3 (planilha .xlsx) para as tabelas
 * assets e transactions, fazendo upsert idempotente: reimportar o mesmo arquivo
 * atualiza os registros em vez de duplicá-los.
 */
class B3MovementImporter
{
    /** Prefixos de produto que representam títulos de renda fixa. */
    private const FIXED_INCOME_PREFIXES = [
        'CDB', 'RDB', 'LC', 'LCA', 'LCI', 'LF', 'LFSN', 'LIG',
        'CRI', 'CRA', 'DEB', 'DEBENTURE', 'TESOURO', 'NTN', 'LTN', 'LFT',
    ];

    /** Palavras que identificam um fundo imobiliário / fiagro no nome do ativo. */
    private const FII_KEYWORDS = [
        'FII', 'IMOBILIARI', 'FIAGRO', 'FDO INV IMOB', 'FDO. INV. IMOB', 'FUNDO IMOB',
    ];

    private int $assetsCreated = 0;

    private int $assetsUpdated = 0;

    private int $transactionsCreated = 0;

    private int $transactionsUpdated = 0;

    private int $skipped = 0;

    private int $incomeCreated = 0;

    private float $incomeTotal = 0.0;

    /** @var array<string, Asset> Cache dos ativos já resolvidos nesta importação. */
    private array $assetCache = [];

    /**
     * Contador de ocorrências de linhas idênticas dentro do arquivo, para que
     * duas operações legitimamente iguais (mesmo dia/ativo/quantidade/preço)
     * não colidam no mesmo external_hash e uma sobrescreva a outra.
     *
     * @var array<string, int>
     */
    private array $rowOccurrences = [];

    /**
     * @return array{assets_created:int,assets_updated:int,transactions_created:int,transactions_updated:int,skipped:int}
     */
    public function import(string $path, Tenant $tenant): array
    {
        $rows = $this->readRows($path);
        $header = array_shift($rows);
        $columns = $this->mapColumns($header);
        $this->rowOccurrences = [];

        // Em massa: observer por linha e auditoria linha a linha só atrapalham;
        // as métricas materializadas são recalculadas de uma vez no final.
        TransactionObserver::$enabled = false;
        activity()->disableLogging();

        try {
            DB::transaction(function () use ($rows, $columns, $tenant) {
                foreach ($rows as $row) {
                    $this->processRow($row, $columns, $tenant);
                }
            });
        } finally {
            TransactionObserver::$enabled = true;
            activity()->enableLogging();
        }

        app(AssetMetricsRefresher::class)->refreshTenant($tenant);

        return [
            'assets_created' => $this->assetsCreated,
            'assets_updated' => $this->assetsUpdated,
            'transactions_created' => $this->transactionsCreated,
            'transactions_updated' => $this->transactionsUpdated,
            'skipped' => $this->skipped,
            'income_created' => $this->incomeCreated,
            'income_total' => round($this->incomeTotal, 2),
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readRows(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);

        return array_values(array_filter($rows, function ($row) {
            return collect($row)->filter(fn ($cell) => $cell !== null && $cell !== '')->isNotEmpty();
        }));
    }

    /**
     * Descobre o índice de cada coluna a partir do cabeçalho (tolerante a acentos).
     *
     * @param  array<int, mixed>  $header
     * @return array<string, int>
     */
    private function mapColumns(array $header): array
    {
        $needles = [
            'direction' => 'entrada',
            'date' => 'data',
            'movement' => 'movimenta',
            'product' => 'produto',
            'institution' => 'institui',
            'quantity' => 'quantidade',
            'unit_price' => 'preco unit',
            'total' => 'valor da opera',
        ];

        $columns = [];
        foreach ($header as $index => $label) {
            $normalized = Str::of((string) $label)->ascii()->lower()->toString();
            foreach ($needles as $key => $needle) {
                if (! isset($columns[$key]) && str_contains($normalized, $needle)) {
                    $columns[$key] = $index;
                }
            }
        }

        $required = ['date', 'movement', 'product'];
        foreach ($required as $key) {
            if (! isset($columns[$key])) {
                throw new \RuntimeException(
                    'A planilha não parece ser um relatório de Movimentação da B3 (cabeçalho não reconhecido).'
                );
            }
        }

        return $columns;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $columns
     */
    private function processRow(array $row, array $columns, Tenant $tenant): void
    {
        $get = fn (string $key) => isset($columns[$key]) ? ($row[$columns[$key]] ?? null) : null;

        $product = trim((string) $get('product'));
        $date = $this->parseDate((string) $get('date'));

        if ($product === '' || $date === null) {
            $this->skipped++;

            return;
        }

        $direction = trim((string) $get('direction'));
        $movement = trim((string) $get('movement'));
        $quantity = $this->parseNumber($get('quantity')) ?? 1.0;
        $unitPrice = $this->parseNumber($get('unit_price'));
        $total = $this->parseNumber($get('total')) ?? (($unitPrice ?? 0) * $quantity);
        $institution = trim((string) $get('institution')) ?: null;

        [$assetType, $code, $name] = $this->parseProduct($product);

        $asset = $this->upsertAsset($tenant, $assetType, $code, $name);

        $hashBase = implode('|', [
            $tenant->getKey(),
            $date->toDateString(),
            $movement,
            $product,
            (string) $quantity,
            (string) ($unitPrice ?? ''),
            (string) $total,
            $direction,
        ]);

        // A 1ª ocorrência mantém o hash sem sufixo para continuar casando com
        // registros importados por versões anteriores.
        $occurrence = $this->rowOccurrences[$hashBase] = ($this->rowOccurrences[$hashBase] ?? 0) + 1;
        $externalHash = sha1($occurrence === 1 ? $hashBase : "{$hashBase}|{$occurrence}");

        $transaction = Transaction::updateOrCreate(
            [
                'tenant_id' => $tenant->getKey(),
                'external_hash' => $externalHash,
            ],
            [
                'asset_id' => $asset->getKey(),
                'type' => $this->mapTransactionType($movement, $direction, $assetType),
                'transaction_date' => $date,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_amount' => $total,
                'direction' => $direction ?: null,
                'movement' => $movement ?: null,
                'institution' => $institution,
                'source' => 'b3',
            ],
        );

        $transaction->wasRecentlyCreated
            ? $this->transactionsCreated++
            : $this->transactionsUpdated++;

        // Proventos novos alimentam o alerta "provento recebido" pós-importação.
        if ($transaction->wasRecentlyCreated && in_array($transaction->type, Asset::CASH_INCOME_TYPES, true)) {
            $this->incomeCreated++;
            $this->incomeTotal += $transaction->isCredit() ? (float) $total : -(float) $total;
        }
    }

    private function upsertAsset(Tenant $tenant, string $type, ?string $code, string $name): Asset
    {
        $cacheKey = $code !== null && $code !== '' ? "code:{$code}" : "name:{$name}|{$type}";

        if (isset($this->assetCache[$cacheKey])) {
            return $this->assetCache[$cacheKey];
        }

        $lookup = $code !== null && $code !== ''
            ? ['tenant_id' => $tenant->getKey(), 'ticker_or_code' => $code]
            : ['tenant_id' => $tenant->getKey(), 'name' => $name, 'type' => $type];

        $asset = Asset::firstOrNew($lookup);
        $asset->fill([
            'name' => $name,
            'type' => $type,
            'ticker_or_code' => $code,
        ]);

        // Guarda o indexador inferido da renda fixa sem sobrescrever ajustes manuais.
        if ($type === 'FIXED_INCOME' && empty($asset->metadata['indexer'] ?? null)) {
            $asset->metadata = array_merge($asset->metadata ?? [], [
                'indexer' => Asset::inferIndexer($name),
                'index_percent' => 100,
                'spread' => 0,
            ]);
        }

        $wasCreated = ! $asset->exists;
        $asset->save();

        $wasCreated
            ? $this->assetsCreated++
            : $this->assetsUpdated++;

        return $this->assetCache[$cacheKey] = $asset;
    }

    /**
     * @return array{0:string,1:?string,2:string} [type, ticker_or_code, name]
     */
    private function parseProduct(string $product): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(' - ', $product)), fn ($p) => $p !== ''));
        $first = $parts[0] ?? $product;
        $firstUpper = Str::upper(Str::ascii($first));

        if (in_array($firstUpper, self::FIXED_INCOME_PREFIXES, true)) {
            $code = $parts[1] ?? $first;

            return ['FIXED_INCOME', $code, $product];
        }

        if (Str::contains(Str::ascii($product), 'Opcao de', true)) {
            $ticker = $this->extractTicker($parts);

            return ['OPTION', $ticker, $product];
        }

        if ($this->looksLikeTicker($first)) {
            $name = count($parts) > 1 ? implode(' - ', array_slice($parts, 1)) : $product;
            $type = $this->isRealEstateFund($first, $name) ? 'FII' : 'STOCK';

            return [$type, $first, $name];
        }

        return ['OTHER', $this->extractTicker($parts), $product];
    }

    /**
     * @param  array<int, string>  $parts
     */
    private function extractTicker(array $parts): ?string
    {
        foreach ($parts as $part) {
            if ($this->looksLikeTicker($part)) {
                return $part;
            }
        }

        return null;
    }

    private function looksLikeTicker(string $value): bool
    {
        return (bool) preg_match('/^[A-Z0-9]{4}[0-9]{1,2}[A-Z]?$/', $value);
    }

    private function isRealEstateFund(string $ticker, string $name): bool
    {
        if (! str_ends_with($ticker, '11')) {
            return false;
        }

        $haystack = Str::upper(Str::ascii($name));

        foreach (self::FII_KEYWORDS as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function mapTransactionType(string $movement, string $direction, string $assetType = 'OTHER'): string
    {
        $m = Str::upper(Str::ascii($movement));
        $isCredit = Str::upper(Str::ascii($direction)) === 'CREDITO';

        return match (true) {
            // Opções têm ciclo próprio: exercício e vencimento encerram a
            // posição (o sentido crédito/débito vem da B3 conforme a posição
            // era comprada ou lançada).
            $assetType === 'OPTION' && str_contains($m, 'EXERCI') => 'EXERCISE',
            $assetType === 'OPTION' && str_contains($m, 'VENCIMENTO') => 'EXPIRE',
            str_contains($m, 'DIVIDENDO') => 'DIVIDEND',
            str_contains($m, 'JUROS SOBRE CAPITAL') => 'JCP',
            str_contains($m, 'PAGAMENTO DE JUROS') => 'INTEREST',
            str_contains($m, 'AMORTIZACAO') => 'AMORTIZATION',
            str_contains($m, 'RENDIMENTO') => 'INCOME',
            str_contains($m, 'BONIFICACAO'), str_contains($m, 'FRACAO EM ATIVOS') => 'BONUS',
            // Crédito em dinheiro pela fração vendida em leilão (a fração em si
            // já saiu da posição no débito "Fração em Ativos").
            str_contains($m, 'LEILAO DE FRACAO') => 'FRACTION_AUCTION',
            str_contains($m, 'SUBSCRICAO') => 'SUBSCRIPTION',
            str_contains($m, 'CESSAO DE DIREITOS') => 'RIGHTS_CESSION',
            // Grupamento: a B3 credita o NOVO TOTAL da posição (sem debitar as
            // ações antigas), então o tipo tem semântica de "reset".
            str_contains($m, 'GRUPAMENTO') => 'GROUPING',
            // Desdobramento: crédito das ações adicionais (delta).
            str_contains($m, 'DESDOBR') => 'SPLIT',
            str_contains($m, 'ATUALIZACAO') => 'UPDATE',
            str_contains($m, 'BLOQUEIO') => 'CUSTODY_BLOCK',
            str_contains($m, 'VENCIMENTO'), str_contains($m, 'RESGATE'), str_contains($m, 'RETIRADA') => 'SELL',
            $m === 'COMPRA' => 'BUY',
            $m === 'VENDA' => 'SELL',
            str_contains($m, 'APLICACAO') => 'BUY',
            str_contains($m, 'LIQUIDACAO') => $isCredit ? 'BUY' : 'SELL',
            str_contains($m, 'COMPRA') && str_contains($m, 'VENDA') => $isCredit ? 'BUY' : 'SELL',
            $m === 'TRANSFERENCIA' => 'TRANSFER',
            default => $isCredit ? 'BUY' : 'SELL',
        };
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $value)->startOfDay();
        } catch (\Throwable) {
            try {
                return Carbon::parse($value)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }
    }

    private function parseNumber(mixed $value): ?float
    {
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = str_replace(['R$', ' ', "\u{00A0}"], '', (string) $value);

        // Formato pt-BR: 1.234,56 -> 1234.56
        if (str_contains($clean, ',')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }
}
