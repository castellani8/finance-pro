<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\B3MovementImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class B3MovementImporterTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = [
        'Entrada/Saída', 'Data', 'Movimentação', 'Produto',
        'Instituição', 'Quantidade', 'Preço unitário', 'Valor da Operação',
    ];

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = new Tenant;
        $this->tenant->forceFill(['name' => 'Tenant de Teste', 'uuid' => (string) Str::uuid()])->save();
    }

    public function test_linhas_identicas_no_mesmo_arquivo_geram_transacoes_distintas(): void
    {
        $row = ['Credito', '20/12/2024', 'COMPRA / VENDA', 'CDB - CDBTESTE123 - BANCO TESTE S.A.', 'CORRETORA', 1000, 1, 1000];

        $result = $this->import([$row, $row]);

        $this->assertSame(2, $result['transactions_created']);
        $this->assertSame(2, Transaction::count());
        $this->assertSame(2000.0, $this->asset('CDBTESTE123')->positionQuantity());
    }

    public function test_reimportar_o_mesmo_arquivo_e_idempotente(): void
    {
        $row = ['Credito', '20/12/2024', 'COMPRA / VENDA', 'CDB - CDBTESTE123 - BANCO TESTE S.A.', 'CORRETORA', 1000, 1, 1000];
        $rows = [$row, $row];

        $this->import($rows);
        $result = $this->import($rows);

        $this->assertSame(0, $result['transactions_created']);
        $this->assertSame(2, $result['transactions_updated']);
        $this->assertSame(2, Transaction::count());
    }

    public function test_grupamento_redefine_a_posicao_e_leilao_de_fracao_nao_altera_quantidade(): void
    {
        // Réplica do ciclo real de um grupamento 20:1 no extrato da B3:
        // 111 ações viram 5,55; a fração de 0,55 sai da custódia e é paga em leilão.
        $this->import([
            ['Credito', '10/12/2024', 'Transferência - Liquidação', 'TEST3 - EMPRESA TESTE S.A.', 'CORRETORA', 30, 2, 60],
            ['Credito', '18/12/2024', 'Transferência - Liquidação', 'TEST3 - EMPRESA TESTE S.A.', 'CORRETORA', 81, 2, 162],
            ['Credito', '01/08/2025', 'Grupamento', 'TEST3 - EMPRESA TESTE S.A.', 'CORRETORA', 5.55, '-', '-'],
            ['Debito', '06/08/2025', 'Fração em Ativos', 'TEST3 - EMPRESA TESTE S.A.', 'CORRETORA', 0.55, '-', '-'],
            ['Credito', '26/08/2025', 'Leilão de Fração', 'TEST3 - EMPRESA TESTE S.A.', 'CORRETORA', 0.55, 0.44, 0.24],
            ['Debito', '28/08/2025', 'Transferência - Liquidação', 'TEST3 - EMPRESA TESTE S.A.', 'CORRETORA', 5, 3, 15],
        ]);

        $asset = $this->asset('TEST3');

        $this->assertEqualsWithDelta(0.0, $asset->positionQuantity(), 1e-9);
        $this->assertEqualsWithDelta(0.24, $asset->dividendsReceived(), 1e-9);
        $this->assertSame(0, Asset::wherePositionPositive()->where('ticker_or_code', 'TEST3')->count());
    }

    public function test_atualizacao_e_ignorada_e_bonificacao_entra_a_custo_zero(): void
    {
        // "Atualização" da B3 informa o TOTAL da posição na data (não um delta),
        // então não pode ser somada; bonificação aumenta a quantidade sem custo.
        $this->import([
            ['Credito', '10/01/2025', 'Transferência - Liquidação', 'BONI3 - BONIFICADORA S.A.', 'CORRETORA', 100, 10, 1000],
            ['Credito', '15/02/2025', 'Atualização', 'BONI3 - BONIFICADORA S.A.', 'CORRETORA', 100, '-', '-'],
            ['Credito', '20/03/2025', 'Bonificação em Ativos', 'BONI3 - BONIFICADORA S.A.', 'CORRETORA', 10, '-', '-'],
        ]);

        $asset = $this->asset('BONI3');

        $this->assertEqualsWithDelta(110.0, $asset->positionQuantity(), 1e-9);
        $this->assertEqualsWithDelta(1000.0 / 110.0, $asset->averageBuyPrice(), 1e-9);
        $this->assertEqualsWithDelta(1000.0, $asset->purchaseValue(), 1e-6);
        $this->assertSame(1, Asset::wherePositionPositive()->where('ticker_or_code', 'BONI3')->count());
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<string, int>
     */
    private function import(array $rows): array
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([self::HEADER, ...$rows]);

        $path = tempnam(sys_get_temp_dir(), 'b3-test-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        try {
            return app(B3MovementImporter::class)->import($path, $this->tenant);
        } finally {
            @unlink($path);
        }
    }

    private function asset(string $tickerOrCode): Asset
    {
        return Asset::with('transactions')->where('ticker_or_code', $tickerOrCode)->firstOrFail();
    }
}
