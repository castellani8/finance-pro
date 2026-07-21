<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Versão por e-mail dos alertas do painel (vencimentos, saldos negativos...).
 * Só é enviada quando NOTIFY_BY_EMAIL=true e o mailer está configurado.
 */
class PortfolioAlertMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting($this->title)
            ->line($this->body);

        if ($this->url !== null) {
            $mail->action('Abrir no painel', $this->url);
        }

        return $mail->salutation('— Finance Pro');
    }
}
