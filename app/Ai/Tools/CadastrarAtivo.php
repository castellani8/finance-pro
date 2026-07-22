<?php

namespace App\Ai\Tools;

use App\Models\Asset;
use App\Models\PortfolioSnapshot;
use App\Models\Transaction;
use App\Support\PortfolioCache;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;

/**
 * Cadastra um ativo novo — espelha o CreateAsset do Filament, inclusive o
 * lançamento de aquisição obrigatório para bens físicos. SEMPRE exige
 * aprovação do usuário antes de executar.
 */
class CadastrarAtivo extends MilhaTool implements Approvable
{
    use InteractsWithApprovals;

    public function description(): string
    {
        return 'Cadastra um ativo novo na carteira (ação, FII, renda fixa, imóvel, veículo...). '
            .'A execução SEMPRE pede confirmação do usuário. Para bens físicos (imóvel, veículo, '
            .'máquina, commodity, colecionável, software, outro) informe também valor_aquisicao e '
            .'data_aquisicao. Antes de chamar, confirme os dados com o usuário; não invente ticker.';
    }

    public function handle(Request $request): string
    {
        $tipo = $request->string('tipo')->toString();
        $nome = trim($request->string('nome')->toString());
        $ticker = mb_strtoupper(trim($request->string('ticker')->toString())) ?: null;
        $moeda = $request->string('moeda')->toString() ?: 'BRL';

        if (! array_key_exists($tipo, Asset::TYPE_LABELS)) {
            return $this->json([
                'erro' => 'tipo inválido.',
                'tipos_validos' => Asset::TYPE_LABELS,
            ]);
        }

        if ($nome === '' || mb_strlen($nome) > 255) {
            return $this->json(['erro' => 'nome é obrigatório (até 255 caracteres).']);
        }

        if (! in_array($moeda, ['BRL', 'USD', 'EUR'], true)) {
            return $this->json(['erro' => 'moeda deve ser BRL, USD ou EUR.']);
        }

        if (in_array($tipo, ['STOCK', 'FII', 'OPTION'], true) && $ticker === null) {
            return $this->json(['erro' => "ticker é obrigatório para o tipo {$tipo}."]);
        }

        if ($ticker !== null) {
            $duplicate = Asset::query()
                ->where('tenant_id', $this->tenant->getKey())
                ->where('ticker_or_code', $ticker)
                ->exists();

            if ($duplicate) {
                return $this->json(['erro' => "Já existe um ativo com o código {$ticker} na carteira."]);
            }
        }

        $isPhysical = in_array($tipo, Asset::PHYSICAL_TYPES, true);
        $valorAquisicao = round($request->float('valor_aquisicao'), 2);
        $dataAquisicao = $request->string('data_aquisicao')->toString() ?: now()->toDateString();

        if ($isPhysical) {
            if ($valorAquisicao <= 0) {
                return $this->json(['erro' => 'valor_aquisicao (maior que zero) é obrigatório para bens físicos.']);
            }

            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataAquisicao)) {
                return $this->json(['erro' => 'data_aquisicao deve estar no formato YYYY-MM-DD.']);
            }
        }

        $asset = Asset::create([
            'tenant_id' => $this->tenant->getKey(),
            'name' => $nome,
            'type' => $tipo,
            'ticker_or_code' => $ticker,
            'currency' => $moeda,
        ]);

        if ($isPhysical) {
            // Bem físico nasce com a aquisição, senão a posição seria zero
            // e nem apareceria na listagem (mesma regra do CreateAsset).
            Transaction::create([
                'tenant_id' => $this->tenant->getKey(),
                'asset_id' => $asset->getKey(),
                'type' => 'BUY',
                'transaction_date' => $dataAquisicao,
                'quantity' => 1,
                'unit_price' => $valorAquisicao,
                'total_amount' => $valorAquisicao,
                'direction' => 'Credito',
                'movement' => 'Aquisição',
                'source' => 'manual',
            ]);
        }

        PortfolioSnapshot::where('tenant_id', $this->tenant->getKey())->delete();
        PortfolioCache::bump($this->tenant->getKey());

        return $this->json([
            'sucesso' => true,
            'ativo' => array_filter([
                'id' => $asset->getKey(),
                'nome' => $nome,
                'classe' => Asset::TYPE_LABELS[$tipo],
                'ticker' => $ticker,
                'moeda' => $moeda,
                'valor_aquisicao' => $isPhysical ? $this->money($valorAquisicao) : null,
            ], fn ($v) => $v !== null),
            'observacao' => in_array($tipo, ['STOCK', 'FII'], true)
                ? 'As movimentações de compra/venda deste ativo entram pela importação da B3 ou pela tela de Assets.'
                : null,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tipo' => $schema->string()
                ->enum(array_keys(Asset::TYPE_LABELS))
                ->description('Classe do ativo: STOCK (ação), FII, FIXED_INCOME, OPTION, VEHICLE, MACHINERY, REAL_ESTATE, COMMODITY, COLLECTIBLE, SOFTWARE, OTHER.')
                ->required(),
            'nome' => $schema->string()
                ->description('Nome do ativo, ex.: "PETROBRAS PN", "CDB Banco X 110% CDI", "Apto Rua das Flores".')
                ->required(),
            'ticker' => $schema->string()
                ->description('Ticker/código, ex.: PETR4, MXRF11. Obrigatório para STOCK, FII e OPTION.'),
            'moeda' => $schema->string()->enum(['BRL', 'USD', 'EUR'])
                ->description('Moeda do ativo. Padrão: BRL.'),
            'valor_aquisicao' => $schema->number()
                ->description('Valor de aquisição em reais — obrigatório para bens físicos.'),
            'data_aquisicao' => $schema->string()
                ->description('Data de aquisição, YYYY-MM-DD — usada em bens físicos. Padrão: hoje.'),
        ];
    }
}
