# Milha — a assistente de IA do painel

Balão flutuante (canto inferior direito) presente em todas as telas
autenticadas do painel. A Milha conversa em português, consulta os dados do
usuário, registra operações e faz papel de coach financeiro — sem nunca
recomendar investimento.

## Arquitetura

```
resources/views/livewire/milha-chat.blade.php   UI (balão + painel, CSS próprio)
app/Livewire/MilhaChat.php                      componente Livewire (estado, aprovações, rate limit)
app/Jobs/MilhaPromptJob.php                     turno da IA na fila (não bloqueia worker web)
app/Ai/Milha.php                                o agente (persona, guarda-corpos, tools)
app/Ai/Tools/*.php                              as tools — única porta da IA para os dados
```

## Fluxo assíncrono (fila + polling)

A chamada à API NUNCA roda no request web — com N usuários conversando ao
mesmo tempo, seriam N workers de PHP presos esperando o Groq:

1. `send()` valida, aplica o rate limit e despacha `MilhaPromptJob` com
   **delay de 1s** — a janela de arrependimento. Enquanto o turno está na
   fila, o botão de enviar vira um **botão de stop** (padrão de mercado) e o
   input trava.
2. `stop()` grava um flag de cancelamento no cache; o job checa o flag antes
   de chamar a API e aborta sem gastar token.
3. O job executa o agente e deposita o resultado no cache
   (`milha:reply:{uuid}`, TTL 10 min): texto, gráficos, aprovações pendentes
   e o id da conversa. Falha dura (timeout/worker morto) grava `error` via
   `failed()` — o chat nunca fica esperando para sempre.
4. O componente faz `wire:poll` de 1,5s (só enquanto espera) e coleta o
   payload. **O scroll posiciona no INÍCIO da resposta nova**, não no fim —
   o usuário lê de cima para baixo sem caçar o scroll. (Ao enviar, o scroll
   desce até a própria mensagem.)
5. Aprovar/recusar segue o mesmo caminho (job sem delay).

Requisito de infra: um worker de fila ativo (`php artisan queue:work` em
dev; Horizon em produção — o job usa a fila default). Sem worker, o chat
fica no indicador de digitação até o payload expirar.

Streaming de tokens ficou de fora de propósito: com a execução na fila, ele
exigiria WebSockets (Reverb/Echo) via `broadcast()` do SDK — é a evolução
natural se quisermos resposta palavra a palavra.

- **SDK**: `laravel/ai` com provider **Groq**, modelo `openai/gpt-oss-120b`
  (pinado em `config/ai.php` e no atributo `#[Model]` do agente).
- **Registro no painel**: render hook `BODY_END` no `AppPanelProvider`, só
  quando há usuário autenticado e tenant resolvido.
- **Conversas**: persistidas pelo próprio SDK nas tabelas
  `agent_conversations` / `agent_conversation_messages` (participant = User).
  O componente recarrega a última conversa no mount — nada se perde entre
  sessões ou navegações.

## Modelo de segurança

1. **Tenant fixado no construtor de toda tool.** O LLM nunca escolhe de quem
   são os dados; a tool nasce amarrada ao tenant da sessão.
2. **`tenantId` é `#[Locked]` no Livewire** e, além disso, toda ação revalida
   `user->tenants()->whereKey($tenantId)->firstOrFail()`.
3. **Escrita só com aprovação.** Toda tool de escrita implementa `Approvable`
   e exige aprovação (padrão do trait). O fluxo pausa, o painel mostra um
   cartão com os argumentos exatos e botões Aprovar/Recusar; a decisão
   continua a execução via `Decision::approveAll()`/`rejectAll()`. Recusa não
   grava nada.
4. **Validação forte nas tools de escrita**: enums de tipo/categoria, datas
   `YYYY-MM-DD`, valores > 0, conta/empresa sempre resolvidas dentro do
   tenant (id de outro tenant → erro).
5. **Números nunca vêm do modelo**: as tools devolvem valores já calculados
   pelos services (`PassiveIncome`, `CashFlow`, `Asset`, `CurrencyConverter`).
6. **Rate limit**: 60 mensagens/usuário/dia (`MilhaChat::DAILY_MESSAGE_LIMIT`).
7. **Markdown sanitizado** (`html_input: strip`, links inseguros bloqueados).

## Tools

| Tool | Faz | Escrita? |
|------|-----|----------|
| ResumoDaCarteira | totais, rentabilidade, posições por classe | não |
| ConsultarProventos | proventos por período/tipo/ticker, soma com sinal | não |
| ConsultarRendaPassiva | renda passiva mensal + progresso da meta | não |
| ConsultarContas | contas e saldos (fonte de `account_id`) | não |
| ConsultarEmpresas | empresas + receitas/despesas 12m | não |
| ConsultarFluxoDeCaixa | receitas × despesas por mês, filtro por empresa | não |
| ConsultarLancamentos | lançamentos avulsos com filtros | não |
| GerarGrafico | gráfico SVG no chat (barras/linha/pizza) | não |
| CriarLancamento | receita/despesa avulsa | ✅ aprovação |
| RegistrarOperacao | compra/venda de ativo (cria o ativo junto se preciso) | ✅ aprovação |
| CriarRecorrencia | assinatura/aluguel/contrato mensal | ✅ aprovação |
| CadastrarAtivo | ativo novo (físico exige valor/data de aquisição) | ✅ aprovação |
| CriarConta | conta bancária/corretora/caixa | ✅ aprovação |
| RegistrarFeedback | reclamação/sugestão/elogio/bug → tabela `milha_feedback` | não* |

\* Feedback grava sem botão de aprovação de propósito (fricção mataria a
coleta), mas a Milha é instruída a confirmar na conversa antes. Consulta:
`MilhaFeedback::where('tipo', 'reclamacao')->latest()`.

Convenções ao criar uma tool nova:
- Estenda `MilhaTool` (tenant no construtor, helpers `json()`/`money()`).
- Escrita ⇒ `implements Approvable` + `use InteractsWithApprovals` e replique
  as invalidações da tela equivalente (`PortfolioSnapshot`/`PortfolioCache`).
- Busca por nome ⇒ `whereRaw('lower(...) like ?')` — LIKE é case-sensitive no
  PostgreSQL.
- Registre no `tools()` do agente, no `ACTION_LABELS` do `MilhaChat` (se
  escrita) e cubra com teste de escopo de tenant em `MilhaToolsTest`.

## Gráficos no chat

A Milha desenha de verdade — as instruções proíbem tabelas/ASCII grandes:

1. O modelo chama a tool `GerarGrafico` com a spec (tipo barras/linha/pizza,
   título, labels, até 3 séries) usando valores vindos das outras tools.
2. A tool valida e empilha a spec no `App\Ai\ChartRegistry` (singleton com
   escopo de request, registrado no `AppServiceProvider`).
3. Após o `prompt()` retornar, o `MilhaChat` drena o registry e renderiza
   cada spec com `App\Ai\ChartSvg` — SVG puro, sem JS, paleta preto & ouro,
   texto em `currentColor` (acompanha tema claro/escuro).
4. Ao recarregar a conversa, os gráficos voltam: o componente reidrata as
   specs a partir dos `tool_calls` persistidos (via `GerarGrafico::normalize`).

Labels/títulos são escapados no SVG (teste de XSS incluso).

## Testes

- `tests/Feature/MilhaToolsTest.php` — escopo por tenant, validações,
  aprovação obrigatória nas escritas, filtro por empresa.
- `tests/Feature/MilhaChatTest.php` — renderização no painel (e ausência no
  login), envio com `Ai::fakeAgent()` (zero tokens), persistência/recarga de
  conversa, `tenantId` travado, usuário intruso bloqueado, erro da API vira
  mensagem amigável.

Rodar: `php artisan test --filter="Milha"` (a suíte também passa em pgsql).

## Por que tools nativas e não MCP (por enquanto)

MCP resolve *transporte entre processos*: expor tools para um cliente de IA
externo (Claude, um bot fora do app). Aqui o agente roda dentro do próprio
Laravel — as tools são chamadas de função no mesmo processo, com o tenant já
resolvido pela sessão. Um servidor MCP adicionaria rede, autenticação por
token e mapeamento de tenant sem ganhar nada.

O MCP entra no roadmap quando a Milha for exposta para fora — e aí o
`laravel/mcp` (pacote oficial) pode publicar ESTAS MESMAS classes de tool,
que não dependem de nada do Filament.

## Milha Vendedora (landing page)

A landing (`/`) tem uma segunda encarnação da Milha, como consultora de vendas
para visitantes anônimos (método AIDA):

```
app/Ai/MilhaVendedora.php                            agente sem tools — conhecimento nas instruções
app/Jobs/MilhaVendedoraJob.php                       turno na fila (mesmo padrão do painel)
app/Livewire/MilhaVendedoraChat.php                  componente (histórico na SESSÃO, nada no banco)
resources/views/livewire/milha-vendedora-chat.blade.php
```

- **Abre sozinha** na primeira visita (1,5s) com abordagem proativa fixa
  (mensagem hardcoded — zero custo de API); quem fecha não é incomodado de
  novo (`localStorage`).
- **Conhecimento**: recursos reais + preço/trial lidos de `config('landing.plan')`
  — nunca inventa recurso, desconto ou número. Fora do produto, redireciona.
- **Freios de abuso** (endpoint público que consome API paga): 20 mensagens/
  sessão/dia + 60/IP/dia, input ≤ 500 caracteres, conversa ≤ 30 mensagens —
  estourou, vira CTA de cadastro.
- **CTA fixo** no rodapé do chat e links markdown para `/app/register` nas
  respostas.
- Testes em `tests/Feature/MilhaVendedoraTest.php`.

### Rastreabilidade dos leads (métricas)

Cada conversa real (pageview sozinho não cria linha) vira registros em:

- `milha_lead_conversations`: session_id, ip, user_agent, **tokens totais**
  (prompt/completion — base para custo por lead), **cta_clicks** e
  **cta_first_clicked_at** (cliques em qualquer CTA de cadastro do chat,
  botão fixo ou link nas respostas).
- `milha_lead_messages`: cada mensagem (user/assistant) com tokens por
  resposta. A resposta é gravada pelo próprio job — a métrica não depende do
  visitante continuar na página.

Métricas prontas: perguntas mais comuns (`role = 'user'`), taxa de conversa →
clique (`cta_first_clicked_at is not null`), custo por conversa (tokens ×
preço do modelo). Tokens do painel já ficam na coluna `usage` de
`agent_conversation_messages` (o SDK grava sozinho).

### Avatar

Os chats procuram `public/images/milha-avatar.jpg` (foto oficial da Milha) e,
se não existir, caem no fallback `milha-avatar.svg` (ilustração própria).
Trocar o visual dela = salvar um novo `milha-avatar.jpg` — nada de código.

### Comportamento mobile (chat da landing)

Em telas ≤ 640px o chat NÃO abre sozinho: o balão fica chacoalhando
(animação `milha-lp-wiggle`) até o primeiro clique. Aberto, vira uma folha
centralizada (74dvh) sobre um overlay escuro com blur, e o balão some.
No desktop o comportamento original permanece (abre sozinho na primeira
visita, ancorado à direita, sem overlay).

## Roadmap WhatsApp/Telegram

O agente já é desacoplado da UI: `(new Milha($tenant, $user))->forUser($user)
->prompt($texto)`. Para um canal novo:
1. Webhook do canal (ex.: Telegram Bot API) → resolver o usuário pelo número/
   chat_id (tabela de vínculo + verificação).
2. Chamar o agente com `->continueLastConversation($user)` — o histórico é o
   mesmo do painel.
3. Aprovações: mapear os botões Aprovar/Recusar para reply buttons do canal
   (`Decision::approveAll()`/`rejectAll()`).
4. Manter o rate limit por usuário e considerar fila para não bloquear o
   webhook.
