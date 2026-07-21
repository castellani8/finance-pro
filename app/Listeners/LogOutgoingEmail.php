<?php

namespace App\Listeners;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\URL;

/**
 * Loga TODO e-mail que sai da aplicação em email_logs — marketing,
 * verificação de e-mail, alertas, reset de senha — e injeta o pixel de
 * rastreio de abertura. Envios feitos pelo EmailLogger (que já criam o
 * próprio log) carregam o header X-Email-Log-Id e são pulados aqui.
 */
class LogOutgoingEmail
{
    public function handle(MessageSending $event): void
    {
        $message = $event->message;
        $headers = $message->getHeaders();

        if ($headers->has('X-Email-Log-Id')) {
            return;
        }

        $to = $message->getTo()[0]?->getAddress();

        if ($to === null) {
            return;
        }

        $log = EmailLog::create([
            'from' => $message->getFrom()[0]?->getAddress() ?? config('mail.from.address'),
            'to' => $to,
            'user_id' => User::where('email', $to)->value('id'),
            'tag' => $headers->has('X-Email-Tag') ? $headers->get('X-Email-Tag')->getBodyAsString() : null,
            'subject' => $message->getSubject(),
        ]);

        $html = $message->getHtmlBody();

        if (is_string($html) && $html !== '') {
            $pixel = '<img src="'.URL::signedRoute('email-logs.read', ['emailLog' => $log->id]).'" alt="" width="1" height="1" style="display: none"/>';

            $html = str_contains($html, '</body>')
                ? str_replace('</body>', $pixel."\n</body>", $html)
                : $html.$pixel;

            $message->html($html);
        }

        $log->update(['html_body' => is_string($html) ? $html : null]);
    }
}
