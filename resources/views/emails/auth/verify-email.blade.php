<x-emails.layout
    title="Confirme seu e-mail"
    action-text="Confirmar e-mail"
    :action-url="$url"
    footnote="Se você não criou uma conta no Milia Invest, pode ignorar este e-mail — nada acontece sem a sua ação."
>
    <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#444;">
        Olá, {{ str($user->name)->before(' ') }}! Que bom ter você por aqui.
        Falta só um passo para começar a organizar seu patrimônio: confirmar que este e-mail é seu.
    </p>
    <p style="margin:0 0 24px; font-size:14px; line-height:1.6; color:#444;">
        O link abaixo vale por {{ config('auth.verification.expire', 60) }} minutos.
        Se expirar, é só pedir um novo na tela de login.
    </p>
</x-emails.layout>
