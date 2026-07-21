# Melhorias opcionais

Pontos identificados em revisão de código que **não são bugs** — o sistema
funciona corretamente sem eles — mas valem a pena quando houver mais dados,
mais usuários ou quando a monetização exigir. Em ordem aproximada de retorno
sobre esforço.

## Performance / escala

1. **Materializar posição e valores no ativo** (colunas `position_quantity`,
   `current_value`, `invested_value` atualizadas na importação/lançamento).
   Hoje `Asset::scopeWherePositionPositive()` e a ordenação de colunas
   calculadas ([AssetsTable::sortByComputed](../app/Filament/Resources/Assets/Tables/AssetsTable.php))
   carregam os ativos do tenant com transações e calculam em PHP a cada
   requisição — ok para centenas de transações, gargalo para dezenas de
   milhares. A materialização elimina os dois de uma vez.
2. **Batch das cotações na listagem**: `currentUnitPrice()` e
   `dailyChangePercent()` fazem 1–2 queries por ticker (com cache estático por
   request). Uma única query trazendo os 2 últimos fechamentos de todos os
   tickers da página reduziria ~50 queries para 1.
3. **Redis em produção**: o `PortfolioCache` usa o padrão de versão justamente
   para funcionar em qualquer driver; com Redis, TTLs e invalidação ficam mais
   baratos e o queue ganha throughput.
4. **Índice em `transactions (tenant_id, type)`** se os agregados de fluxo de
   caixa ficarem lentos com milhões de linhas (hoje o índice
   `(tenant_id, transaction_date)` cobre bem).

## Robustez

5. **Trava no gerador de recorrências**: `Cache::lock('recurring-gen-{tenant}')`
   ou índice único `(recurring_transaction_id, transaction_date)` para eliminar
   a janela teórica de duplicação entre o cron e o botão manual.
6. **Auditoria de lançamentos manuais** (ex: `spatie/laravel-activitylog`):
   quem alterou o quê — importante quando houver multiusuário por tenant.
7. **Testes de UI** (Livewire/Filament): a suíte cobre serviços e models; forms
   e tabelas são verificados por instanciação/reflection, não por interação.

## Produto

8. **Contas e conciliação bancária** — resolve a limitação nº 1
   ([limitacoes.md](limitacoes.md)): entidade Conta com saldo + importação
   OFX/CSV + casamento automático com lançamentos. Candidata natural a tier pago.
9. **Multi-moeda com conversão** (série de câmbio no `marketing_index_series`
   e conversão na valoração).
10. **Cotação automática de físicos**: tabela FIPE para veículos, grama do ouro
    para commodities — hoje dependem de reavaliação manual.
11. **Apuração de ganho de capital / DARF mensal** no módulo IR (prejuízo
    compensável, isenção de 20k, day-trade separado).
12. **Notificações**: provento recebido, recorrência gerada, contrato de
    recorrência perto do `ends_on`, ativo sem cotação há N dias.
13. **Billing** (Laravel Cashier + Stripe/Pix) com feature flags por plano — a
    estrutura multi-tenant já suporta.
14. **Visão de renda passiva consolidada**: aluguel + proventos + juros num
    painel único com meta mensal (estilo "viver de renda").

## Manutenção

15. **Extrair a lógica de sinal (Crédito/Débito)** para um enum/value object —
    hoje `isCredit()` + convenções por tipo estão espalhadas entre
    `Transaction`, `CashFlow` e `Company::sumTransactions`.
16. **Resetar o memo de `chronologicalTransactions`** quando a relação for
    recarregada (ver limitação técnica em limitacoes.md) — ex: sobrescrever
    `setRelation()` no model.
