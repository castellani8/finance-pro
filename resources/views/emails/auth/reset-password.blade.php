<x-emails.layout
    title="Vamos redefinir sua senha"
    action-text="Redefinir senha"
    :action-url="$url"
    footnote="Se você não pediu a redefinição, pode ignorar este e-mail — sua senha continua a mesma."
>
    <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#444;">
        Olá, {{ str($user->name)->before(' ') }}. Recebemos um pedido para redefinir a senha
        da sua conta no Milia Invest.
    </p>
    <p style="margin:0 0 24px; font-size:14px; line-height:1.6; color:#444;">
        Clique no botão abaixo para escolher uma nova senha.
        O link vale por {{ $expireMinutes }} minutos.
    </p>
</x-emails.layout>
