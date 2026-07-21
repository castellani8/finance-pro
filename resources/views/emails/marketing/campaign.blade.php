<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $campaign->subject($user) }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #F4F1EA; font-family: 'Instrument Sans', 'Segoe UI', Arial, sans-serif;">
    {{-- Preheader (prévia oculta nos clientes de e-mail) --}}
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">
        {{ $campaign->preheader($user) }}&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #F4F1EA;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%;">

                    {{-- Cabeçalho preto & ouro --}}
                    <tr>
                        <td style="background-color: #0A0A0A; border-radius: 14px 14px 0 0; padding: 26px 40px; border-bottom: 2px solid #D4AF37;">
                            <a href="{{ url('/') }}" style="text-decoration: none;">
                                <img src="{{ asset('images/email/logo.png') }}" alt="Milia Invest" width="180" style="display: block; border: 0; max-width: 180px; height: auto;">
                            </a>
                        </td>
                    </tr>

                    {{-- Conteúdo --}}
                    <tr>
                        <td style="background-color: #FFFFFF; padding: 40px;">
                            <h1 style="margin: 0 0 18px; font-size: 24px; line-height: 1.3; letter-spacing: -0.3px; color: #111111;">
                                {{ $campaign->headline($user) }}
                            </h1>

                            @foreach ($campaign->paragraphs($user) as $paragraph)
                                <p style="margin: 0 0 16px; font-size: 15px; line-height: 1.65; color: #444444;">
                                    {{ $paragraph }}
                                </p>
                            @endforeach

                            @if ($campaign->bullets($user) !== [])
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 8px 0 20px;">
                                    @foreach ($campaign->bullets($user) as $bullet)
                                        <tr>
                                            <td valign="top" style="width: 22px; padding: 5px 0; color: #B18F27; font-weight: 700; font-size: 15px;">✓</td>
                                            <td style="padding: 5px 0; font-size: 14px; line-height: 1.55; color: #444444;">{{ $bullet }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 10px 0 6px;">
                                <tr>
                                    <td style="border-radius: 999px; background-color: #D4AF37;">
                                        <a href="{{ $campaign->ctaUrl() }}"
                                            style="display: inline-block; padding: 14px 34px; font-size: 15px; font-weight: 700; color: #0A0A0A; text-decoration: none; border-radius: 999px;">
                                            {{ $campaign->ctaLabel() }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Rodapé --}}
                    <tr>
                        <td style="background-color: #0A0A0A; border-radius: 0 0 14px 14px; padding: 28px 40px;">
                            <p style="margin: 0 0 10px; font-size: 12px; line-height: 1.6; color: #8A8A8A;">
                                Você recebeu este e-mail porque criou uma conta na Milia Invest.
                                <a href="{{ $unsubscribeUrl }}" style="color: #D4AF37; text-decoration: underline;">Não quero mais receber estes e-mails</a>.
                            </p>
                            <p style="margin: 0 0 10px; font-size: 11px; line-height: 1.6; color: #6E6E6E;">
                                O Milia Invest não faz recomendação de investimento. Os dados exibidos têm caráter informativo e podem conter atrasos ou imprecisões.
                            </p>
                            <p style="margin: 0; font-size: 11px; color: #6E6E6E;">
                                © {{ date('Y') }} Milia Invest ·
                                <a href="{{ route('legal.privacidade') }}" style="color: #8A8A8A; text-decoration: underline;">Política de Privacidade</a> ·
                                <a href="mailto:{{ config('landing.contact.email') }}" style="color: #8A8A8A; text-decoration: underline;">{{ config('landing.contact.email') }}</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
