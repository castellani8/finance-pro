<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Notifications\NoticeOfEmailChangeRequest;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Auth\Notifications\VerifyEmailChange;
use Tests\TestCase;

/**
 * Garante que os e-mails de autenticação do Filament saem com os templates
 * da marca (binds no AppServiceProvider) e renderizam com o conteúdo certo.
 */
class AuthEmailsTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->make(['name' => 'Lucas Castellani']);
    }

    public function test_container_resolve_as_notificacoes_com_a_marca(): void
    {
        $this->assertInstanceOf(\App\Notifications\Auth\VerifyEmail::class, app(VerifyEmail::class));
        $this->assertInstanceOf(\App\Notifications\Auth\ResetPassword::class, app(ResetPassword::class, ['token' => 'token-teste']));
        $this->assertInstanceOf(\App\Notifications\Auth\VerifyEmailChange::class, app(VerifyEmailChange::class));
        $this->assertInstanceOf(\App\Notifications\Auth\NoticeOfEmailChangeRequest::class, app(NoticeOfEmailChangeRequest::class, [
            'newEmail' => 'novo@example.com',
            'blockVerificationUrl' => 'https://example.com/bloquear',
        ]));
    }

    public function test_email_de_verificacao_renderiza_com_a_marca(): void
    {
        $notification = app(VerifyEmail::class);
        $notification->url = 'https://example.com/verificar';

        $mail = $notification->toMail($this->user);
        $html = $mail->render();

        $this->assertSame('Confirme seu e-mail — Milia Invest', $mail->subject);
        $this->assertStringContainsString('MILIA INVEST', $html);
        $this->assertStringContainsString('Olá, Lucas!', $html);
        $this->assertStringContainsString('https://example.com/verificar', $html);
        $this->assertStringContainsString('Confirmar e-mail', $html);
    }

    public function test_email_de_redefinicao_de_senha_renderiza_com_a_marca(): void
    {
        $notification = app(ResetPassword::class, ['token' => 'token-teste']);
        $notification->url = 'https://example.com/redefinir';

        $mail = $notification->toMail($this->user);
        $html = $mail->render();

        $this->assertSame('Redefina sua senha — Milia Invest', $mail->subject);
        $this->assertStringContainsString('MILIA INVEST', $html);
        $this->assertStringContainsString('https://example.com/redefinir', $html);
        $this->assertStringContainsString('60 minutos', $html);
        $this->assertStringContainsString('sua senha continua a mesma', $html);
    }

    public function test_email_de_troca_de_email_renderiza_com_a_marca(): void
    {
        $notification = app(VerifyEmailChange::class);
        $notification->url = 'https://example.com/confirmar-troca';

        $mail = $notification->toMail($this->user);
        $html = $mail->render();

        $this->assertSame('Confirme seu novo e-mail — Milia Invest', $mail->subject);
        $this->assertStringContainsString('MILIA INVEST', $html);
        $this->assertStringContainsString('https://example.com/confirmar-troca', $html);
    }

    public function test_email_de_aviso_de_troca_renderiza_com_a_marca(): void
    {
        $notification = app(NoticeOfEmailChangeRequest::class, [
            'newEmail' => 'novo@example.com',
            'blockVerificationUrl' => 'https://example.com/bloquear',
        ]);

        $mail = $notification->toMail($this->user);
        $html = $mail->render();

        $this->assertSame('Pedido de troca de e-mail — Milia Invest', $mail->subject);
        $this->assertStringContainsString('MILIA INVEST', $html);
        $this->assertStringContainsString('novo@example.com', $html);
        $this->assertStringContainsString('https://example.com/bloquear', $html);
        $this->assertStringContainsString('Bloquear a troca', $html);
    }
}
