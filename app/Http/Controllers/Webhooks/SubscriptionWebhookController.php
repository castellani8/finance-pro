<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSubscriptionWebhookJob;
use App\Models\WebhookLog;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

/**
 * Endpoint único de webhooks de assinatura para qualquer gateway.
 *
 * A rota /webhooks/{gateway} resolve o driver correspondente, valida a
 * autenticidade da requisição pelo próprio driver e despacha o processamento
 * assíncrono. Adicionar um novo provedor não exige novo controller.
 */
class SubscriptionWebhookController extends Controller
{
    public function handle(Request $request, string $gateway, PaymentGatewayManager $manager): Response
    {
        try {
            $driver = $manager->driver($gateway);
        } catch (InvalidArgumentException) {
            return response()->noContent(404);
        }

        if (! $driver->verifyWebhook($request)) {
            WebhookLog::create([
                'source' => $gateway,
                'event' => $request->input('event', 'unknown'),
                'payload' => $request->all(),
                'status' => 'failed',
                'error' => 'Falha na verificação de autenticidade do webhook',
            ]);

            return response()->noContent(401);
        }

        $log = WebhookLog::create([
            'source' => $gateway,
            'event' => $request->input('event', 'unknown'),
            'payload' => $request->all(),
            'status' => 'received',
        ]);

        ProcessSubscriptionWebhookJob::dispatch($log->id, $gateway, $request->all());

        // Sempre 200 para evitar reenvios do provedor.
        return response()->noContent();
    }
}
