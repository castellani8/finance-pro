<x-emails.layout
    title="Confirme seu novo e-mail"
    action-text="Confirmar novo e-mail"
    :action-url="$url"
    footnote="Se você não reconhece este pedido, pode ignorar este e-mail — a troca só acontece com a confirmação."
>
    <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#444;">
        Olá, {{ str($user->name)->before(' ') }}. Você pediu para trocar o e-mail de acesso
        da sua conta no Milia Invest para este endereço.
    </p>
    <p style="margin:0 0 24px; font-size:14px; line-height:1.6; color:#444;">
        Para confirmar a troca, clique no botão abaixo.
        O link vale por {{ config('auth.verification.expire', 60) }} minutos.
    </p>
</x-emails.layout>
