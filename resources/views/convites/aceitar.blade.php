<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Convite — Milia Invest</title>
    <style>
        * { box-sizing: border-box; margin: 0; }
        body { background: #0A0A0A; color: #e5e5e5; font-family: -apple-system, 'Segoe UI', Arial, sans-serif; min-height: 100vh; display: grid; place-items: center; padding: 1.5rem; }
        .card { max-width: 460px; width: 100%; background: #171717; border: 1px solid rgba(212,175,55,.35); border-radius: 1rem; padding: 2rem; text-align: center; }
        .brand { color: #D4AF37; font-weight: 700; letter-spacing: 3px; font-size: .75rem; }
        h1 { font-size: 1.25rem; margin: 1rem 0 .75rem; color: #fff; }
        p { font-size: .9rem; line-height: 1.6; color: #a3a3a3; margin-bottom: .75rem; }
        .btn { display: inline-block; background: #D4AF37; color: #0A0A0A; font-weight: 700; border: 0; border-radius: 999px; padding: .8rem 2rem; font-size: .95rem; cursor: pointer; text-decoration: none; margin-top: .75rem; }
        .btn-ghost { display: inline-block; color: #D4AF37; font-size: .85rem; text-decoration: none; margin-top: 1rem; }
    </style>
</head>
<body>
<div class="card">
    <span class="brand">MILIA INVEST</span>

    @if ($status === 'valido')
        <h1>{{ str($invitation->inviter?->name ?? 'Alguém')->before(' ') }} convidou você para a carteira
            "{{ $invitation->tenant->name }}"</h1>

        @if ($user)
            <p>Você está conectado como <strong>{{ $user->email }}</strong>. Ao aceitar, essa
                carteira aparece no seu painel e vocês passam a ver o mesmo patrimônio.</p>
            <form method="POST" action="{{ route('convite.confirmar', ['token' => $invitation->token]) }}">
                @csrf
                <button type="submit" class="btn">Aceitar convite</button>
            </form>
        @else
            <p>Para aceitar, entre na sua conta (ou crie uma gratuitamente com o e-mail
                <strong>{{ $invitation->email }}</strong>) e depois abra o link do convite de novo.</p>
            <a class="btn" href="{{ url('/app/login') }}">Entrar</a>
            <br>
            <a class="btn-ghost" href="{{ url('/app/register') }}">Ainda não tenho conta →</a>
        @endif
    @elseif ($status === 'ja-membro')
        <h1>Você já participa desta carteira</h1>
        <p>"{{ $invitation->tenant->name }}" já está disponível no seu painel.</p>
        <a class="btn" href="{{ url('/app/'.$invitation->tenant->getKey()) }}">Abrir painel</a>
    @elseif ($status === 'aceito')
        <h1>Este convite já foi utilizado</h1>
        <p>Se foi você quem aceitou, é só entrar no painel. Caso contrário, peça um novo convite.</p>
        <a class="btn" href="{{ url('/app') }}">Ir para o painel</a>
    @elseif ($status === 'expirado')
        <h1>Este convite expirou</h1>
        <p>Convites valem por {{ \App\Models\TenantInvitation::VALID_DAYS }} dias. Peça para a pessoa
            enviar um novo na página "Família" do painel.</p>
    @else
        <h1>Convite não encontrado</h1>
        <p>O link pode estar incompleto ou o convite foi revogado. Confira o e-mail original
            ou peça um novo convite.</p>
    @endif
</div>
</body>
</html>
