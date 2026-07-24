<?php

namespace App\Notifications\Auth;

use Filament\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPassword extends BaseResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Redefina sua senha — Milia Invest')
            ->view('emails.auth.reset-password', [
                'user' => $notifiable,
                'url' => $this->resetUrl($notifiable),
                'expireMinutes' => config('auth.passwords.' . config('auth.defaults.passwords') . '.expire'),
            ]);
    }
}
