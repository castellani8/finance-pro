<?php

namespace App\Http\Controllers;

use App\Models\TenantInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Aceite de convite do modo família. O GET apresenta o convite em qualquer
 * estado (válido, expirado, já aceito); o POST efetiva o vínculo e exige
 * usuário autenticado no painel.
 */
class ConviteController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $invitation = TenantInvitation::query()->with(['tenant', 'inviter'])->where('token', $token)->first();

        $status = match (true) {
            $invitation === null => 'invalido',
            $invitation->accepted_at !== null => 'aceito',
            $invitation->isExpired() => 'expirado',
            default => 'valido',
        };

        $user = $request->user();

        if ($status === 'valido' && $user !== null && $user->canAccessTenant($invitation->tenant)) {
            $status = 'ja-membro';
        }

        return view('convites.aceitar', [
            'invitation' => $invitation,
            'status' => $status,
            'user' => $user,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = TenantInvitation::query()->with('tenant')->where('token', $token)->firstOrFail();

        abort_if($invitation->accepted_at !== null || $invitation->isExpired(), 410);

        $user = $request->user();

        if ($user === null) {
            // Volta para cá depois do login/cadastro no painel.
            return redirect()->guest(url('/app/login'));
        }

        // syncWithoutDetaching: idempotente se a pessoa já for membro.
        $invitation->tenant->users()->syncWithoutDetaching([$user->getKey()]);
        $invitation->forceFill(['accepted_at' => now()])->save();

        return redirect(url('/app/'.$invitation->tenant->getKey()));
    }
}
