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

**Preparado para o Asaas**: campos `asaas_customer_id` e `asaas_subscription_id` já existem. A integração futura deve: criar cliente+assinatura no Asaas no clique de "Assinar agora", e nos webhooks (`PAYMENT_CONFIRMED`, `PAYMENT_OVERDUE`, `SUBSCRIPTION_DELETED`…) atualizar `status`/`current_period_ends_at`.

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
