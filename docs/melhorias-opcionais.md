# Melhorias opcionais

Pontos identificados em revisões de código que **não são bugs** — o sistema
funciona corretamente sem eles — mas valem a pena quando houver mais dados,
mais usuários ou quando a monetização exigir.

## Performance / escala

1. **Redis em produção**: o `PortfolioCache` usa o padrão de versão justamente
   para funcionar em qualquer driver; com Redis, TTLs, o lock do gerador de
   recorrências e o queue ficam mais robustos.

## Produto

2. **Importação de extrato bancário (OFX/CSV) e conciliação**: as Contas já
   existem com saldo entrando no patrimônio; o próximo passo é importar o
   extrato do banco e casar automaticamente com os lançamentos, detectando o
   que falta lançar. Candidata natural a tier pago.
3. **Multi-moeda — refinamentos**: USD/EUR já funcionam de ponta a ponta
   (PTAX diária via `marketing:sync-currencies`, custo pelo câmbio da data da
   operação, valor pelo câmbio do dia, contas em moeda estrangeira). Arestas
   que restam: somatório do rodapé da página Proventos não converte (soma SQL
   em moeda nativa — só aparece com proventos estrangeiros), lançamentos
   avulsos sem conta são sempre BRL, e outras moedas além de USD/EUR exigem
   apenas adicionar a série SGS correspondente ao command.
4. **Cotação automática de físicos**: tabela FIPE para veículos, grama do ouro
   para commodities — hoje dependem de reavaliação manual.
5. **Fonte de cotações contratada**: o histórico usa o endpoint público do
   Yahoo (informal). Decisão registrada: o scraping vai para um sistema open
   source separado que alimenta `asset_price_history` (mesmo schema:
   `ticker, date, open/close/high/low, source`) — o app só lê da tabela,
   então a troca é transparente.
6. **Apuração de ganho de capital — refinamentos**: a apuração mensal com
   isenção de 20k, DARF e compensação de prejuízo já existe no relatório IR;
   faltam day-trade separado (alíquota 20% e regras próprias) e ETFs/BDRs
   (sem isenção).
7. **Notificações — canal push**: sino do painel e e-mail (NOTIFY_BY_EMAIL)
   já existem para todos os gatilhos; push só faz sentido quando houver app
   mobile ou PWA.
8. **Billing** (Laravel Cashier + Stripe/Pix) com feature flags por plano — a
   estrutura multi-tenant já suporta.

## Notas de modelagem (comportamentos por design, não pendências)

- A evolução do patrimônio usa o *último preço conhecido ≤ data*; ativos sem
  cotação caem para o custo. Snapshots diários são recalculáveis (import ou
  edição apaga e regenera) — não são um registro histórico imutável.
- As métricas materializadas do ativo (`position_quantity`, `invested_value`,
  `current_value`) são mantidas pelo observer de transações, pelo job diário
  de snapshot e pelo botão "Atualizar valores" — filtro e ordenação usam as
  colunas; a exibição continua calculada ao vivo.
- Lançamentos avulsos **sem conta** são só fluxo de caixa (não mexem no
  patrimônio); vinculados a uma conta, movem o saldo — e o saldo é patrimônio.
- A auditoria registra ações humanas; importação da B3 e lançamentos gerados
  por recorrência ficam de fora de propósito (têm origem própria no extrato).
- O relatório IR é apoio ao preenchimento: os códigos de grupo são sugestões e
  os informes das corretoras continuam sendo a fonte oficial.
