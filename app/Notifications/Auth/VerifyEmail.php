<?php

namespace App\Notifications\Auth;

use Filament\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmail extends BaseVerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirme seu e-mail — Milia Invest')
            ->view('emails.auth.verify-email', [
                'user' => $notifiable,
                'url' => $this->verificationUrl($notifiable),
            ]);
    }
}
