<?php

namespace App\Mail;

use App\Models\User;
use App\Services\Marketing\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * E-mail de campanha de marketing: template único (identidade preto & ouro)
 * alimentado pelo conteúdo da campanha, com link de descadastro assinado e
 * headers List-Unsubscribe (one-click) para deliverability.
 */
class MarketingCampaignMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Campaign $campaign,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->campaign->subject($this->user),
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'List-Unsubscribe' => '<'.$this->unsubscribeUrl().'>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.marketing.campaign',
            with: [
                'user' => $this->user,
                'campaign' => $this->campaign,
                'unsubscribeUrl' => $this->unsubscribeUrl(),
            ],
        );
    }

    private function unsubscribeUrl(): string
    {
        return URL::signedRoute('marketing.unsubscribe', ['user' => $this->user]);
    }
}
