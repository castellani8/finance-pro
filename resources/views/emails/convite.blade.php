<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Convite — Milia Invest</title>
</head>
<body style="margin:0; padding:0; background-color:#F4F1EA; font-family: Arial, Helvetica, sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F4F1EA; padding: 24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px; width:100%;">
                <tr>
                    <td style="background-color:#0A0A0A; border-radius:12px 12px 0 0; border-bottom:3px solid #D4AF37; padding:20px 28px;">
                        <span style="color:#D4AF37; font-size:13px; font-weight:bold; letter-spacing:3px;">MILIA INVEST</span>
                    </td>
                </tr>
                <tr>
                    <td style="background-color:#ffffff; padding:28px;">
                        <h1 style="margin:0 0 12px; font-size:20px; color:#0A0A0A;">
                            {{ str($invitation->inviter?->name ?? 'Alguém')->before(' ') }} convidou você
                            para acompanhar a carteira "{{ $invitation->tenant->name }}"
                        </h1>
                        <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#444;">
                            No modo família do Milia Invest, vocês enxergam o mesmo patrimônio:
                            investimentos, imóveis, contas, renda passiva e relatórios — tudo numa visão só.
                        </p>
                        <p style="margin:0 0 24px; font-size:14px; line-height:1.6; color:#444;">
                            O convite vale por {{ \App\Models\TenantInvitation::VALID_DAYS }} dias e foi feito
                            para <strong>{{ $invitation->email }}</strong>. Se você ainda não tem conta,
                            crie uma gratuitamente com este e-mail e clique no botão de novo.
                        </p>
                        <table role="presentation" cellpadding="0" cellspacing="0" align="center">
                            <tr>
                                <td style="background-color:#D4AF37; border-radius:999px;">
                                    <a href="{{ $acceptUrl }}"
                                       style="display:inline-block; padding:13px 32px; font-size:15px; font-weight:bold; color:#0A0A0A; text-decoration:none;">
                                        Aceitar convite
                                    </a>
                                </td>
                            </tr>
                        </table>
                        <p style="margin:24px 0 0; font-size:12px; color:#999;">
                            Não esperava este convite? Pode ignorar este e-mail — nada acontece sem a sua ação.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color:#0A0A0A; border-radius:0 0 12px 12px; padding:16px 28px;">
                        <p style="margin:0; font-size:11px; color:#888;">
                            Milia Invest · {{ config('landing.contact.email') }}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
