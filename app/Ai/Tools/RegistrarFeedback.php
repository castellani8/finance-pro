<?php

namespace App\Ai\Tools;

use App\Models\MilhaFeedback;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Registra feedback do cliente sobre o PRODUTO (reclamação, sugestão, elogio,
 * bug) para o time do Milia Invest. Sem aprovação de propósito: fricção aqui
 * mataria a coleta — e o registro é inofensivo e só diz respeito à opinião
 * do próprio usuário.
 */
class RegistrarFeedback extends MilhaTool
{
    public function __construct(Tenant $tenant, private readonly User $user)
    {
        parent::__construct($tenant);
    }

    public function description(): string
    {
        return 'Registra um feedback do usuário sobre o Milia Invest para o time do produto: '
            .'reclamação, sugestão de melhoria, elogio ou relato de bug. Use quando o usuário '
            .'reclamar de algo do sistema, sugerir uma funcionalidade, elogiar ou descrever um '
            .'erro — confirme com ele em uma frase antes de registrar. NÃO é para dúvidas nem '
            .'para assuntos de investimento.';
    }

    public function handle(Request $request): string
    {
        $tipo = $request->string('tipo')->toString();
        $mensagem = trim($request->string('mensagem')->toString());
        $contexto = trim($request->string('contexto')->toString()) ?: null;

        if (! in_array($tipo, MilhaFeedback::TIPOS, true)) {
            return $this->json(['erro' => 'tipo inválido.', 'tipos_validos' => MilhaFeedback::TIPOS]);
        }

        if ($mensagem === '' || mb_strlen($mensagem) > 2000) {
            return $this->json(['erro' => 'mensagem é obrigatória (até 2000 caracteres).']);
        }

        $feedback = MilhaFeedback::create([
            'tenant_id' => $this->tenant->getKey(),
            'user_id' => $this->user->getKey(),
            'tipo' => $tipo,
            'mensagem' => $mensagem,
            'contexto' => $contexto !== null ? mb_strimwidth($contexto, 0, 255, '…') : null,
        ]);

        return $this->json([
            'sucesso' => true,
            'feedback_id' => $feedback->getKey(),
            'observacao' => 'Feedback registrado para o time do Milia Invest. Agradeça o usuário '
                .'com carinho — feedback é presente.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tipo' => $schema->string()->enum(MilhaFeedback::TIPOS)
                ->description('reclamacao, sugestao, elogio, bug ou outro.')
                ->required(),
            'mensagem' => $schema->string()
                ->description('O feedback com as palavras do usuário, fiel ao que ele disse.')
                ->required(),
            'contexto' => $schema->string()
                ->description('Opcional: tela ou assunto relacionado, ex.: "Relatório IR", "importação B3".'),
        ];
    }
}
