# Assinaturas e e-mail marketing

## Controle de assinaturas

Plano único (R$ definido em `config/landing.php`) com trial de 15 dias, uma assinatura por usuário (`subscriptions`, model `App\Models\Subscription`).

**Ciclo de vida** (`App\Enums\SubscriptionStatus`):

```
cadastro ──> trialing ──(trial vence)──> expired
                │
                └──(Asaas: pagamento)──> active ──> past_due ──> canceled ──> expired
```

- O trial é criado em `Register::handleRegistration`; usuários pré-existentes foram backfillados na migration com trial a partir do próprio `created_at`.
- `subscriptions:sync-status` (cron 09:00) materializa expirações.
- Acesso ao painel: `User::hasPanelAccess()` → middleware `EnsureSubscriptionIsActive` (authMiddleware do panel). Sem acesso, o usuário só navega para **Assinatura**, perfil e logout; o resto redireciona para a página de assinatura.
- Página Filament **Assinatura** (`/app/{tenant}/assinatura`): status, dias restantes, plano e CTA de assinar (placeholder até o Asaas).

### Pagamento (Asaas)

A cobrança é feita pelo **Asaas** via camada agnóstica de gateway (`app/Services/Payments`, driver em `Gateways/AsaasGateway.php` sobre o client `app/Services/Asaas`):

- **Assinar**: página **Assinatura** → modal com PIX, boleto ou cartão (checkout transparente). Cartão ativa na hora; PIX/boleto redirecionam para a fatura e o acesso é liberado quando o webhook confirmar. `subscriptions` guarda `gateway`, `billing_type`, `asaas_customer_id`, `asaas_subscription_id` e `latest_invoice_url`.
- **Webhooks**: `POST /webhooks/{gateway}` (CSRF isento), autenticado pelo header `asaas-access-token` contra `ASAAS_WEBHOOK_AUTH_TOKEN`. Cada evento vira uma linha em `webhook_logs` e é processado async por `ProcessSubscriptionWebhookJob` (idempotente, com lock): `PAYMENT_CONFIRMED` ativa e soma um ciclo ao período pago; `PAYMENT_OVERDUE` → past_due (acesso mantido em carência); `PAYMENT_REFUNDED` → expired; `SUBSCRIPTION_DELETED` → canceled com acesso até o fim do período pago. O usuário é notificado no sino do painel.
- **Cancelar**: botão na página Assinatura chama o gateway e aplica a mesma regra de carência.
- **Env**: `ASAAS_ACCESS_TOKEN`, `ASAAS_BASE_URL` (sandbox por padrão; produção `https://api.asaas.com/v3`), `ASAAS_WEBHOOK_AUTH_TOKEN`, `SUBSCRIPTION_GATEWAY=asaas`. Planos em `config/subscription.php` (preço acompanha `LANDING_PLAN_PRICE`).

### Verificação de e-mail

Novos cadastros precisam confirmar o e-mail (`User` implementa `MustVerifyEmail` + `->emailVerification()` no panel). Contas anteriores à exigência foram marcadas como verificadas na migration.

### Log de e-mails (email_logs)

Todo e-mail que sai da aplicação é registrado em `email_logs` pelo listener `LogOutgoingEmail` (evento `MessageSending`): remetente, destinatário, usuário, tag (header `X-Email-Tag`), assunto, HTML e abertura (`read_at`, via pixel assinado `/email/pixel/{id}`). Para enviar uma **Notification** com tag e log garantidos, use `App\Utils\EmailLogger` (`EmailLogger::make($notification, $user)->tag('...')->log()`) — envios dele carregam `X-Email-Log-Id` e não são duplicados pelo listener.

## Funil de e-mail marketing

Régua de ciclo de vida focada em ativação → hábito → conversão → retenção. Enviada por `marketing:send-campaigns` (cron 09:30, após o sync de assinaturas), **no máximo 1 e-mail por usuário por dia**, cada campanha **no máximo 1 vez por usuário** (unique em `marketing_email_sends`).

| Dia | Campanha (`key`) | Segmento | Objetivo |
|---|---|---|---|
| 0 | `welcome` | todos (enviada na hora, via listener) | Primeiro "uau": importar planilha B3 |
| 3 | `activation_d3` | carteira sem movimentações | Destravar a ativação |
| 7 | `spotlight_d7` | todos em teste | Criar hábito: benchmarks, renda passiva, alertas |
| T-3 | `trial_ending` | assinatura `trialing` com ≤3 dias | Conversão com urgência honesta |
| T+1..3 | `trial_ended` | assinatura `expired` | Última chamada; dados guardados |
| 30–44 | `winback_d30` | com acesso, inativo ≥14 dias (auditoria) | Reengajamento |

Prioridade quando mais de uma está devida: conversão (`trial_ending`/`trial_ended`) > ativação > conteúdo (ordem em `CampaignManager::campaigns()`).

**Janela com tolerância**: cada campanha só dispara até `grace_days` (config `marketing.grace_days`, padrão 2) após o dia ideal — e-mail atrasado demais perde o contexto e é pulado.

### Compliance e deliverability

- **Opt-out em 1 clique**: link assinado no rodapé (`/email/descadastrar/{user}`) + headers `List-Unsubscribe` e `List-Unsubscribe-Post` (RFC 8058). Página de confirmação permite reativar.
- Opt-out bloqueia só marketing; alertas transacionais da carteira continuam.
- Template único (`resources/views/emails/marketing/campaign.blade.php`) na identidade preto & ouro, layout em tabelas (Outlook/Gmail), logo PNG em `public/images/email/logo.png`, preheader oculto.

### Operação

- Desligar tudo: `MARKETING_EMAILS_ENABLED=false`.
- Ensaiar sem enviar: `php artisan marketing:send-campaigns --dry-run`.
- Envio é via fila (`ShouldQueue`) — requer worker (`queue:listen`/`queue:work`).
- Nova campanha: criar classe em `app/Services/Marketing/Campaigns` estendendo `Campaign` e registrá-la em `CampaignManager::campaigns()` na posição de prioridade certa.
