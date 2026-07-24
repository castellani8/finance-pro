<?php

namespace App\Notifications\Auth;

use Filament\Auth\Notifications\NoticeOfEmailChangeRequest as BaseNoticeOfEmailChangeRequest;
use Illuminate\Notifications\Messages\MailMessage;

class NoticeOfEmailChangeRequest extends BaseNoticeOfEmailChangeRequest
{
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pedido de troca de e-mail — Milia Invest')
            ->view('emails.auth.notice-of-email-change-request', [
                'user' => $notifiable,
                'newEmail' => $this->newEmail,
                'blockUrl' => $this->blockVerificationUrl,
            ]);
    }
}
