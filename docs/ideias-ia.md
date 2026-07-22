# Ideias de implementação com IA

O projeto usa o **laravel/ai** (SDK oficial da Laravel) com o **Groq** como
provider padrão (`config/ai.php`, chave em `GROQ_API_KEY`). Modelos padrão do
driver: `openai/gpt-oss-120b` (default/smartest) e `openai/gpt-oss-20b`
(cheapest). O Groq cobra centavos por milhão de tokens e responde em fração de
segundo, então o custo por usuário/mês das ideias abaixo é irrisório — o
critério de priorização aqui é **entrega percebida ÷ esforço de implementação**.

Como usar no código (referência rápida):

```php
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

final class MinhaIdeia implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'Você é um assistente da Milia Invest...';
    }
}

$resposta = (new MinhaIdeia)->prompt($texto);   // $resposta->text
```

O SDK também suporta saída estruturada (`ObjectSchema`), streaming, tools e
fakes para teste (`Ai::fake()`). Para classificações em lote, usar o modelo
`cheapest` (atributo `#[UseCheapestModel]`) e fila — nunca chamar a API dentro
de request web síncrono.

## Resumo executivo

| # | Ideia | Entrega | Esforço | Custo/uso |
|---|-------|---------|---------|-----------|
| 1 | Categorização automática de lançamentos | Alta | Baixo | ~R$ 0,001/lote |
| 2 | Resumo mensal da carteira por e-mail | Alta | Baixo | ~R$ 0,002/usuário |
| 3 | "Explicar" eventos de provento na tela | Média-alta | Baixo | ~R$ 0,0005/clique |
| 4 | Relatório IR em linguagem simples | Alta | Baixo-médio | ~R$ 0,003/relatório |
| 5 | Alertas humanizados (notify-alerts) | Média | Muito baixo | desprezível |
| 6 | Detecção de anomalias nos lançamentos | Média-alta | Médio | ~R$ 0,002/varredura |
| 7 | Copy de campanhas de marketing | Média | Baixo | desprezível |
| 8 | Chat "pergunte sobre sua carteira" | Alta | Alto | maior (tools/contexto) |

## Detalhamento

### 1. Categorização automática de lançamentos ⭐ começar por aqui

**O quê:** ao criar/importar um lançamento avulso sem categoria, um agente
classifica pela descrição ("PIX PADARIA SILVA" → Alimentação) usando saída
estruturada com a lista fixa de categorias do sistema.

**Por quê:** é o recurso de IA mais pedido em apps de finanças e o usuário
percebe valor no primeiro uso. Roda no modelo cheapest, em lote, via fila.

**Como:** job disparado após a criação de lançamentos sem categoria (tela de
Lançamentos e futura importação OFX/CSV — ver `docs/melhorias-opcionais.md`
item 2). Prompt com a lista de categorias válidas + saída estruturada
(`ObjectSchema` com enum) para nunca inventar categoria. Marcar a origem
(`categorizado por IA`) e deixar o usuário corrigir — a correção pode
alimentar exemplos few-shot por tenant depois.

### 2. Resumo mensal da carteira por e-mail

**O quê:** todo dia 1º, um e-mail "Seu mês na Milia" com 4-6 frases geradas
por IA: proventos recebidos vs. mês anterior, maior pagador, evolução do
patrimônio, meta de renda passiva, e um aviso relevante (renda fixa vencendo).

**Por quê:** retenção. A infraestrutura de e-mail marketing e agendamento
**já existe** (`app/Services/Marketing`, `SendMarketingCampaigns`, Horizon) —
o incremento é só montar o contexto (números que `PassiveIncome`, `CashFlow` e
`PortfolioEvolution` já calculam) e pedir o texto ao agente. Os números vão
prontos no prompt; a IA só redige — nunca calcula.

**Cuidado:** instruir o agente a não fazer recomendação de investimento
(disclaimer do produto) e validar que todo número citado veio do contexto.

### 3. Botão "explicar" em eventos de provento

**O quê:** ícone de ajuda em tipos que confundem (amortização, JCP, leilão de
fração, subscrição) que abre um modal com explicação em 2-3 frases,
contextualizada com o ativo e o valor da linha.

**Por quê:** educação financeira embutida diferencia o produto e o custo é de
um clique ocasional. Cachear a resposta por (tipo, classe do ativo) — depois
do primeiro clique, os demais usuários nem chamam a API.

**Como:** Filament Action na tabela de Proventos → agente com prompt curto →
cache. Meio dia de trabalho.

### 4. Relatório IR em linguagem simples

**O quê:** em cada seção do Relatório IR (`app/Filament/Pages/RelatorioIr.php`,
`app/Services/IrReport.php`), um parágrafo gerado explicando o que declarar,
em qual ficha do programa da Receita, e por que aqueles números — usando os
próprios valores do relatório no texto.

**Por quê:** IR é a maior dor do investidor brasileiro e o momento de maior
percepção de valor do produto (o usuário renova a assinatura na época da
declaração). Gerar uma vez por (tenant, ano) e cachear — custo fixo minúsculo.

### 5. Alertas humanizados

**O quê:** o `portfolio:notify-alerts` monta textos fixos ("CDB X vence em 7
dias"). Passar o conjunto de alertas do dia pelo agente para gerar uma única
notificação coesa e amigável em vez de N avisos secos.

**Por quê:** esforço quase nulo (o comando e o `AlertDispatcher` já existem) e
melhora o tom do produto. Fallback: se a API falhar, envia os textos fixos —
nunca deixar de alertar por causa da IA.

### 6. Detecção de anomalias nos lançamentos

**O quê:** varredura semanal por tenant: lançamentos duplicados, tarifa nova
que não existia, recorrência que subiu de valor, débito atípico. Saída
estruturada com lista de achados → vira notificação no sino.

**Por quê:** é o tipo de insight "uau, ele viu isso sozinho". Custo baixo
(uma chamada semanal por tenant com um extrato resumido), mas exige montar bem
o contexto e limitar falsos positivos — começar só com 2-3 tipos de anomalia.

### 7. Copy de campanhas de marketing

**O quê:** no admin de campanhas (`app/Services/Marketing/Campaigns`), botão
"gerar variações" de assunto/corpo a partir do objetivo da campanha, para
teste A/B.

**Por quê:** ferramenta interna — sem risco para o usuário final, esforço de
uma Action no Filament, e melhora métrica de abertura dos e-mails que já são
enviados.

### 8. Chat "pergunte sobre sua carteira" — ✅ IMPLEMENTADO (Milha)

Implementado como a **Milha**, o balão flutuante do painel — incluindo as
ações de escrita com aprovação (lançamentos, operações de compra/venda,
recorrências, ativos e contas). Arquitetura, segurança e roadmap
WhatsApp/Telegram em [milha-assistente.md](milha-assistente.md).

### 8.1. Texto original da ideia (histórico)

**O quê:** assistente conversacional no painel ("quanto recebi de FIIs em
2025?", "qual conta paga o cartão?") usando tools do laravel/ai que consultam
os services existentes (`PassiveIncome`, `CashFlow`, `IrReport`...) — nunca
SQL livre.

**Por quê está por último:** é a maior entrega, mas exige desenhar tools
seguras (escopo por tenant obrigatório em toda tool), UI de chat, streaming,
limites de uso por plano e guarda-corpos contra alucinação de números. Fazer
depois que 2-3 ideias simples estiverem rodando e medidas.

## Notas técnicas gerais

- **Sempre via fila.** Groq é rápido, mas chamada externa em request web trava
  o Livewire. Exceção: ideias 3 e 8 (interativas), com streaming/loading.
- **Números nunca vêm da IA.** Em todas as ideias o cálculo é dos services
  existentes; a IA só classifica ou redige. Isso elimina o risco de alucinação
  financeira e mantém a promessa do disclaimer do produto.
- **Custo sob controle:** modelo cheapest para classificação (ideias 1, 6),
  default para redação (2, 4); cache agressivo nas ideias 3 e 4; rate limit
  por tenant antes de qualquer recurso interativo.
- **Testes:** o SDK tem `Ai::fake()` — os testes de feature não gastam token
  nem dependem de rede.
- **LGPD:** os prompts enviam dados financeiros do usuário para a API do Groq.
  Antes de lançar, cobrir isso na Política de Privacidade (página
  Privacidade e Dados já existe) e enviar o mínimo necessário (agregados em
  vez de extrato bruto sempre que possível).
