<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

/**
 * Opt-out/opt-in de e-mail marketing via links assinados (não exigem login —
 * o usuário clica direto do e-mail). O POST no unsubscribe atende o
 * one-click do header List-Unsubscribe (RFC 8058).
 */
class MarketingEmailController extends Controller
{
    public function unsubscribe(User $user): View
    {
        $user->forceFill(['marketing_emails_unsubscribed_at' => now()])->save();

        return view('marketing.unsubscribed', ['user' => $user, 'resubscribed' => false]);
    }

    public function resubscribe(User $user): View
    {
        $user->forceFill(['marketing_emails_unsubscribed_at' => null])->save();

        return view('marketing.unsubscribed', ['user' => $user, 'resubscribed' => true]);
    }
}
