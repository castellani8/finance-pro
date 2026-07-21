<?php

namespace App\Utils;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Mailable de HTML pronto usado pelo EmailLogger. Carrega o id do email_log
 * no header X-Email-Log-Id para o listener LogOutgoingEmail não duplicar o
 * registro, e injeta o pixel de abertura no corpo.
 */
class InlineMailable extends Mailable
{
    use Queueable, SerializesModels;

    protected string $inlineSubject;

    protected string $htmlBody;

    protected ?int $emailLogId = null;

    public function __construct(string $subject, string $htmlBody, ?int $emailLogId = null)
    {
        $this->inlineSubject = $subject;
        $this->htmlBody = $htmlBody;
        $this->emailLogId = $emailLogId;
    }

    public function build()
    {
        $html = $this->htmlBody;

        if ($this->emailLogId) {
            $trackImg = '<img src="'.URL::signedRoute('email-logs.read', ['emailLog' => $this->emailLogId]).'" alt="" width="1" height="1" style="display: none"/>';

            $html = str_contains($html, '</body>')
                ? str_replace('</body>', $trackImg."\n</body>", $html)
                : $html.$trackImg;

            $this->withSymfonyMessage(function ($message) {
                $message->getHeaders()->addTextHeader('X-Email-Log-Id', (string) $this->emailLogId);
            });
        }

        return $this->subject($this->inlineSubject)
            ->html($html);
    }
}
