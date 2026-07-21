<?php

namespace App\Utils;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;

/**
 * Envia uma Notification por e-mail registrando tudo em email_logs (com tag
 * e pixel de abertura). Para Mailables comuns não é preciso usar este helper:
 * o listener LogOutgoingEmail já loga qualquer e-mail que sai da aplicação —
 * use o EmailLogger quando quiser controlar a tag e o vínculo com o usuário.
 */
class EmailLogger
{
    private ?string $tag = null;

    public function __construct(public readonly Notification $notification, public readonly User $notifiable) {}

    public static function make(Notification $notification, User $notifiable): static
    {
        return new static($notification, $notifiable);
    }

    public function tag(string $tag): static
    {
        $this->tag = $tag;

        return $this;
    }

    public function log(): void
    {
        if (empty($this->notifiable->email)) {
            return;
        }

        $emailLog = EmailLog::create([
            'from' => config('mail.from.address'),
            'to' => $this->notifiable->email,
            'user_id' => $this->notifiable->id,
            'tag' => $this->tag,
        ]);

        if (! filter_var($this->notifiable->email, FILTER_VALIDATE_EMAIL)) {
            Log::error('Email inválido: '.$this->notifiable->email);
            $emailLog->update(['error_log' => 'Email inválido: '.$this->notifiable->email]);

            return;
        }

        $mailable = $this->notification->toMail($this->notifiable);

        try {
            $markdown = app(Markdown::class);
            $rendered = $markdown->render('notifications::email', $mailable->toArray());
            $html = $rendered instanceof HtmlString ? $rendered->toHtml() : (string) $rendered;

            $emailLog->update([
                'subject' => $mailable->subject,
                'html_body' => $html,
                'action_url' => $mailable->actionUrl,
            ]);

            $inline = new InlineMailable($mailable->subject, $html, $emailLog->id);

            foreach ($mailable->attachments as $attachment) {
                $inline->attach($attachment['file'], $attachment['options']);
            }

            $this->notification instanceof ShouldQueue
                ? Mail::to($this->notifiable->email)->queue($inline)
                : Mail::to($this->notifiable->email)->send($inline);

        } catch (\Throwable $e) {
            report($e);
            $emailLog->update(['error_log' => $e->getMessage()]);
        }
    }
}
