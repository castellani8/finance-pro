<?php

namespace App\Mail;

use App\Models\TenantInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/** Convite do modo família para participar de uma carteira no Milia Invest. */
class TenantInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public TenantInvitation $invitation) {}

    public function envelope(): Envelope
    {
        $inviter = $this->invitation->inviter?->name ?? 'Alguém';

        return new Envelope(
            subject: str($inviter)->before(' ')." convidou você para a carteira \"{$this->invitation->tenant->name}\" no Milia Invest",
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: ['X-Email-Tag' => 'convite:familia']);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.convite',
            with: [
                'invitation' => $this->invitation,
                'acceptUrl' => route('convite.aceitar', ['token' => $this->invitation->token]),
            ],
        );
    }
}
