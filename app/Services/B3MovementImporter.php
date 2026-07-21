<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Tenant;
use App\Models\Transaction;
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

    /** @var array<string, Asset> Cache dos ativos já resolvidos nesta importação. */
    private array $assetCache = [];

    /**
     * @return array{assets_created:int,assets_updated:int,transactions_created:int,transactions_updated:int,skipped:int}
     */
    public function import(string $path, Tenant $tenant): array
    {
        $rows = $this->readRows($path);
        $header = array_shift($rows);
        $columns = $this->mapColumns($header);

        DB::transaction(function () use ($rows, $columns, $tenant) {
            foreach ($rows as $row) {
                $this->processRow($row, $columns, $tenant);
            }
        });

        return [
            'assets_created' => $this->assetsCreated,
            'assets_updated' => $this->assetsUpdated,
            'transactions_created' => $this->transactionsCreated,
            'transactions_updated' => $this->transactionsUpdated,
            'skipped' => $this->skipped,
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

        $externalHash = sha1(implode('|', [
            $tenant->getKey(),
            $date->toDateString(),
            $movement,
            $product,
            (string) $quantity,
            (string) ($unitPrice ?? ''),
            (string) $total,
            $direction,
        ]));

        $transaction = Transaction::updateOrCreate(
            [
                'tenant_id' => $tenant->getKey(),
                'external_hash' => $externalHash,
            ],
            [
                'asset_id' => $asset->getKey(),
                'type' => $this->mapTransactionType($movement, $direction),
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

        $asset = Asset::updateOrCreate($lookup, [
            'name' => $name,
            'type' => $type,
            'ticker_or_code' => $code,
        ]);

        $asset->wasRecentlyCreated
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

    private function mapTransactionType(string $movement, string $direction): string
    {
        $m = Str::upper(Str::ascii($movement));
        $isCredit = Str::upper(Str::ascii($direction)) === 'CREDITO';

        return match (true) {
            str_contains($m, 'DIVIDENDO') => 'DIVIDEND',
            str_contains($m, 'JUROS SOBRE CAPITAL') => 'JCP',
            str_contains($m, 'PAGAMENTO DE JUROS') => 'INTEREST',
            str_contains($m, 'RENDIMENTO') => 'INCOME',
            str_contains($m, 'BONIFICACAO'), str_contains($m, 'FRACAO EM ATIVOS') => 'BONUS',
            str_contains($m, 'LEILAO DE FRACAO') => 'SELL',
            str_contains($m, 'SUBSCRICAO') => 'SUBSCRIPTION',
            str_contains($m, 'CESSAO DE DIREITOS') => 'RIGHTS_CESSION',
            str_contains($m, 'GRUPAMENTO'), str_contains($m, 'DESDOBR') => 'GROUPING',
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
