<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Pixel de rastreio: marca o e-mail como lido e devolve um GIF transparente
 * de 1x1. A URL é assinada, então não dá para forjar leituras.
 */
class EmailPixelController extends Controller
{
    public function read(EmailLog $emailLog): Response
    {
        if ($emailLog->read_at === null) {
            $emailLog->update(['read_at' => now()]);
        }

        return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'))
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
