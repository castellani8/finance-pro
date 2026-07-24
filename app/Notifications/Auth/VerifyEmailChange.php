<?php

namespace App\Notifications\Auth;

use Filament\Auth\Notifications\VerifyEmailChange as BaseVerifyEmailChange;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailChange extends BaseVerifyEmailChange
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirme seu novo e-mail — Milia Invest')
            ->view('emails.auth.verify-email-change', [
                'user' => $notifiable,
                'url' => $this->verificationUrl($notifiable),
            ]);
    }
}
