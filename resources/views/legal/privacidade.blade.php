<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Política de Privacidade — Finance Pro</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 760px; margin: 0 auto; padding: 2rem 1.25rem 4rem; color: #1f2937; line-height: 1.65; }
        h1 { font-size: 1.6rem; } h2 { font-size: 1.15rem; margin-top: 2rem; }
        a { color: #b45309; }
        .updated { color: #6b7280; font-size: .875rem; }
        .disclaimer { background: #fffbeb; border: 1px solid #fcd34d; border-radius: .5rem; padding: 1rem; margin-top: 2rem; }
    </style>
</head>
<body>
    <h1>Política de Privacidade e Proteção de Dados</h1>
    <p class="updated">Última atualização: {{ \Illuminate\Support\Carbon::parse('2026-07-21')->locale('pt_BR')->translatedFormat('d \d\e F \d\e Y') }}</p>

    <p>Esta política descreve como o <strong>Finance Pro</strong> trata seus dados pessoais, em conformidade com a Lei Geral de Proteção de Dados (Lei nº 13.709/2018 — LGPD).</p>

    <h2>1. Quais dados coletamos</h2>
    <ul>
        <li><strong>Dados cadastrais:</strong> nome, e-mail e senha (armazenada com hash irreversível).</li>
        <li><strong>Dados financeiros que você importa:</strong> movimentações do extrato da B3 (ativos, quantidades, valores, corretoras) e ativos cadastrados manualmente. Esses dados pertencem a você e são usados exclusivamente para exibir a sua própria carteira.</li>
        <li><strong>Dados de mercado públicos:</strong> cotações e índices econômicos obtidos de fontes públicas da internet, sem vínculo com a sua identidade.</li>
    </ul>

    <h2>2. Para que usamos</h2>
    <p>Exclusivamente para operar as funcionalidades da plataforma: consolidação de carteira, cálculo de posições, rentabilidade, proventos e relatórios. <strong>Não vendemos, compartilhamos ou cedemos seus dados a terceiros.</strong></p>

    <h2>3. Base legal</h2>
    <p>O tratamento é fundamentado na <em>execução de contrato</em> (art. 7º, V, LGPD) — os dados são necessários para prestar o serviço que você contratou — e, para comunicações opcionais, no seu <em>consentimento</em> (art. 7º, I).</p>

    <h2>4. Seus direitos (art. 18, LGPD)</h2>
    <p>A qualquer momento, dentro da plataforma (menu <strong>Privacidade e dados</strong>), você pode:</p>
    <ul>
        <li><strong>Acessar e exportar</strong> todos os seus dados em formato legível por máquina (portabilidade);</li>
        <li><strong>Corrigir</strong> dados incompletos ou desatualizados;</li>
        <li><strong>Excluir sua conta</strong> e todos os dados financeiros associados, de forma definitiva e irreversível.</li>
    </ul>

    <h2>5. Retenção e segurança</h2>
    <p>Seus dados são mantidos enquanto sua conta existir. Ao excluir a conta, todos os dados financeiros são apagados imediatamente do banco de dados de produção. Senhas usam hash bcrypt; o acesso à plataforma exige autenticação e cada carteira é isolada por tenant.</p>

    <h2>6. Contato</h2>
    <p>Dúvidas sobre esta política ou solicitações relacionadas a dados pessoais: <a href="mailto:privacidade@financepro.app">privacidade@financepro.app</a>.</p>

    <div class="disclaimer">
        <strong>Aviso importante:</strong> o Finance Pro é uma ferramenta de organização e acompanhamento de investimentos. As informações exibidas <strong>não constituem recomendação, oferta ou análise de investimento</strong> nos termos da regulamentação da CVM. Decisões de investimento são de sua exclusiva responsabilidade.
    </div>

    <p style="margin-top:2rem"><a href="{{ url('/app') }}">← Voltar para a plataforma</a></p>
</body>
</html>
