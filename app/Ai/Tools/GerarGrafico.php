<?php

namespace App\Ai\Tools;

use App\Ai\ChartRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * A Milha "desenha" chamando esta tool: a spec validada vai para o
 * ChartRegistry e o chat renderiza um SVG de verdade — nada de tabela ASCII.
 * O normalize() estático também reidrata gráficos ao recarregar a conversa
 * (a partir dos tool_calls persistidos).
 */
class GerarGrafico extends MilhaTool
{
    private const TIPOS = ['barras', 'linha', 'pizza'];

    public function description(): string
    {
        return 'Exibe um gráfico visual para o usuário no chat (barras, linha ou pizza). '
            .'Use SEMPRE que for mostrar evolução, comparação ou distribuição — no lugar de '
            .'tabelas ou desenhos em texto. Os valores devem vir das outras tools, nunca '
            .'inventados. Após gerar, comente o gráfico em no máximo duas frases.';
    }

    public function handle(Request $request): string
    {
        [$spec, $erro] = self::normalize($request->all());

        if ($erro !== null) {
            return $this->json(['erro' => $erro]);
        }

        app(ChartRegistry::class)->push($spec);

        return $this->json([
            'sucesso' => true,
            'observacao' => "O gráfico \"{$spec['titulo']}\" já está sendo exibido ao usuário no chat. "
                .'NÃO repita os dados em texto nem monte tabelas — apenas comente em 1-2 frases.',
        ]);
    }

    /**
     * Valida os argumentos crus e devolve [spec, erro]: exatamente um dos
     * dois é não-nulo.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{0: ?array<string, mixed>, 1: ?string}
     */
    public static function normalize(array $arguments): array
    {
        $tipo = (string) ($arguments['tipo'] ?? '');
        $titulo = trim((string) ($arguments['titulo'] ?? ''));
        $labels = array_values((array) ($arguments['labels'] ?? []));
        $series = array_values((array) ($arguments['series'] ?? []));
        $moeda = (bool) ($arguments['valores_em_reais'] ?? true);

        if (! in_array($tipo, self::TIPOS, true)) {
            return [null, 'tipo deve ser barras, linha ou pizza.'];
        }

        if ($titulo === '') {
            return [null, 'titulo é obrigatório.'];
        }

        $maxLabels = $tipo === 'pizza' ? 8 : 24;

        if (count($labels) < 1 || count($labels) > $maxLabels) {
            return [null, "labels deve ter entre 1 e {$maxLabels} itens para {$tipo}."];
        }

        $labels = array_map(fn ($l): string => trim((string) $l), $labels);

        $maxSeries = $tipo === 'pizza' ? 1 : 3;

        if (count($series) < 1 || count($series) > $maxSeries) {
            return [null, "series deve ter entre 1 e {$maxSeries} séries para {$tipo}."];
        }

        $normalizadas = [];

        foreach ($series as $i => $serie) {
            $serie = (array) $serie;
            $valores = array_values((array) ($serie['valores'] ?? []));

            if (count($valores) !== count($labels)) {
                return [null, 'Cada série precisa de exatamente um valor por label.'];
            }

            foreach ($valores as $v) {
                if (! is_numeric($v)) {
                    return [null, 'Todos os valores devem ser numéricos.'];
                }

                if ($tipo === 'pizza' && $v < 0) {
                    return [null, 'Gráfico de pizza não aceita valores negativos — use barras.'];
                }
            }

            $normalizadas[] = [
                'nome' => trim((string) ($serie['nome'] ?? 'Série '.($i + 1))),
                'valores' => array_map(fn ($v): float => (float) $v, $valores),
            ];
        }

        return [[
            'tipo' => $tipo,
            'titulo' => $titulo,
            'labels' => $labels,
            'series' => $normalizadas,
            'moeda' => $moeda,
        ], null];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tipo' => $schema->string()->enum(self::TIPOS)
                ->description('barras (comparações), linha (evolução no tempo) ou pizza (distribuição).')
                ->required(),
            'titulo' => $schema->string()
                ->description('Título curto do gráfico, ex.: "Renda passiva — últimos 6 meses".')
                ->required(),
            'labels' => $schema->array()->items($schema->string())
                ->description('Rótulos do eixo X (ou das fatias na pizza), ex.: ["fev/26", "mar/26"].')
                ->required(),
            'series' => $schema->array()->items($schema->object([
                'nome' => $schema->string()->description('Nome da série, ex.: "Receitas".'),
                'valores' => $schema->array()->items($schema->number())
                    ->description('Um valor numérico por label, na mesma ordem.')->required(),
            ]))
                ->description('1 a 3 séries (pizza: exatamente 1). Ex.: [{"nome": "Receitas", "valores": [100, 200]}].')
                ->required(),
            'valores_em_reais' => $schema->boolean()
                ->description('true (padrão) prefixa os valores com R$; false para quantidades.'),
        ];
    }
}
