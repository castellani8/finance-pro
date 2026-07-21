# Limitações conhecidas

Registro honesto do que o sistema **não faz** hoje, com o porquê. Nada aqui é bug —
são decisões de escopo. Quando alguma virar necessidade, veja as sugestões em
[melhorias-opcionais.md](melhorias-opcionais.md).

## 1. Não existe conceito de conta / caixa / saldo

**A principal.** O sistema registra *que* a Fazenda X gastou R$ 100 na assinatura
do Claude Code, mas esse dinheiro não sai de nenhum lugar rastreado — não há
contas bancárias, saldos nem conciliação. Consequências práticas:

- O fluxo de caixa mostra entradas/saídas **lançadas**, não o extrato real do banco.
- Uma despesa avulsa não reduz o patrimônio total (o dinheiro gasto não era um
  ativo rastreado antes de sair).
- Não há como detectar lançamentos esquecidos comparando com o extrato bancário.

Quando fizer sentido, o caminho é criar a entidade "Conta" (banco, corretora,
caixa físico) com saldo e importação de extrato (OFX/CSV) — feature clássica de
tier pago.

## 2. Moeda é cosmética

O campo `currency` existe no ativo (BRL/USD/EUR), mas **todos os cálculos tratam
os valores como uma moeda só**. Um ativo em USD soma com os de BRL sem conversão.
Não use moedas mistas até existir conversão cambial.

## 3. Valoração — aproximações conscientes

- **Depreciação sobre a base inteira**: a taxa linear incide sobre
  aquisição + benfeitorias juntas, contadas a partir da aquisição (ou da última
  reavaliação, que zera o relógio). Uma benfeitoria recente não tem "relógio
  próprio". A reavaliação manual corrige qualquer desvio acumulado.
- **Custo após grupamento é aproximado**: o preço médio por ação muda de base no
  reset do grupamento; `purchaseValue()` de um papel que passou por grupamento e
  continua em carteira pode divergir do custo fiscal exato.
- **Bonificação entra a custo zero**: a Receita permite usar o custo informado
  pela empresa; o sistema não tem esse dado.
- **Renda fixa**: IPCA/IGP-M são séries mensais — compras no meio do mês não têm
  pró-rata dentro do mês. CDI/SELIC são diários e precisos.
- **Evolução do patrimônio**: cada ponto usa o *último preço conhecido ≤ data*;
  ativos sem cotação caem para o custo. Snapshots diários são recalculáveis
  (import/edição apaga e regenera) — não são um registro histórico imutável.

## 4. Relatório IR é apoio, não apuração

- Não calcula **ganho de capital nem DARF mensal** (vendas acima da isenção são
  apenas sinalizadas).
- Os códigos de grupo/código são **sugestões** — confira no programa da Receita.
- Não usa os informes de rendimentos das corretoras como fonte da verdade.

## 5. Cotações vêm de endpoint informal

O histórico de preços usa o endpoint público do Yahoo Finance (sem contrato) e o
catálogo de tickers usa a brapi. Decisão registrada: o scraping será movido para
um sistema open source separado que alimentará a tabela `asset_price_history`
(mesmo schema: `ticker, date, open/close/high/low, source`) — o app só lê dessa
tabela, então a troca é transparente.

## 6. Lançamentos avulsos ficam fora do patrimônio e do IR

Por design: lançamento sem ativo é **fluxo de dinheiro**, não patrimônio. Eles
aparecem no fluxo de caixa e no resultado da empresa, mas não alteram evolução
do patrimônio, valor de ativos nem o relatório IR.

## 7. Detalhes técnicos que valem saber

- **`Asset::chronologicalTransactions()` é memoizado por instância**: se você
  recarregar a relação (`$asset->load('transactions')`) depois de já ter chamado
  um cálculo, o memo fica velho — use `$asset->fresh()->load('transactions')`
  (padrão usado nos testes).
- **Somatório da página Proventos** (rodapé) soma valores absolutos: um estorno
  em débito é somado, não subtraído. Os cálculos de verdade
  (`dividendsReceived()` etc.) tratam o sinal corretamente.
- **Gerador de recorrências não tem trava de concorrência**: rodar o botão
  "Gerar pendentes agora" no exato momento do cron pode, em teoria, duplicar um
  vencimento (janela de milissegundos; ver melhorias).
