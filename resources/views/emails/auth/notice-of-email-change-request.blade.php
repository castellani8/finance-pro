<x-emails.layout
    title="Pedido de troca de e-mail"
    action-text="Bloquear a troca"
    :action-url="$blockUrl"
>
    <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#444;">
        Olá, {{ str($user->name)->before(' ') }}. Recebemos um pedido para trocar o e-mail
        de acesso da sua conta no Milia Invest para <strong>{{ $newEmail }}</strong>.
    </p>
    <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#444;">
        Se foi você, não precisa fazer nada — basta confirmar pelo link que enviamos ao novo endereço.
    </p>
    <p style="margin:0 0 24px; font-size:14px; line-height:1.6; color:#444;">
        <strong>Se não foi você</strong>, clique no botão abaixo para bloquear a troca
        e manter sua conta protegida. Recomendamos também redefinir sua senha.
    </p>
</x-emails.layout>
