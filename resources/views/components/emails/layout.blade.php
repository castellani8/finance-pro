{{--
    Layout base dos e-mails transacionais do Milia Invest (preto & dourado).
    Tabelas + estilos inline por compatibilidade com clientes de e-mail.
--}}
@props([
    'title',
    'actionText' => null,
    'actionUrl' => null,
    'footnote' => null,
])
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — Milia Invest</title>
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
                        <h1 style="margin:0 0 12px; font-size:20px; color:#0A0A0A;">{{ $title }}</h1>
                        {{ $slot }}
                        @if ($actionText && $actionUrl)
                            <table role="presentation" cellpadding="0" cellspacing="0" align="center">
                                <tr>
                                    <td style="background-color:#D4AF37; border-radius:999px;">
                                        <a href="{{ $actionUrl }}"
                                           style="display:inline-block; padding:13px 32px; font-size:15px; font-weight:bold; color:#0A0A0A; text-decoration:none;">
                                            {{ $actionText }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:20px 0 0; font-size:12px; color:#999; word-break:break-all;">
                                Se o botão não funcionar, copie e cole este endereço no navegador:<br>
                                <a href="{{ $actionUrl }}" style="color:#B08D1F;">{{ $actionUrl }}</a>
                            </p>
                        @endif
                        @if ($footnote)
                            <p style="margin:24px 0 0; font-size:12px; color:#999;">{{ $footnote }}</p>
                        @endif
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
