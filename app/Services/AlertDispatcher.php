<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PortfolioAlertMail;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/**
 * Canal único de alertas: sino do painel (database notification) e,
 * opcionalmente, e-mail (NOTIFY_BY_EMAIL=true). Alertas com o mesmo título
 * ainda não lidos não são reenviados — um saldo negativo não vira spam diário.
 */
class AlertDispatcher
{
    public function send(User $user, string $title, string $body, ?string $url = null, string $level = 'warning'): bool
    {
        if ($this->hasUnreadWithTitle($user, $title)) {
            return false;
        }

        $notification = Notification::make()
            ->title($title)
            ->body($body)
            ->{$level}();

        if ($url !== null) {
            $notification->actions([
                Action::make('open')->label('Abrir')->url($url),
            ]);
        }

        $notification->sendToDatabase($user);

        if (config('services.notifications.mail')) {
            $user->notify(new PortfolioAlertMail($title, $body, $url));
        }

        return true;
    }

    private function hasUnreadWithTitle(User $user, string $title): bool
    {
        return DB::table('notifications')
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->whereNull('read_at')
            ->get(['data'])
            ->contains(fn (object $row): bool => (json_decode((string) $row->data, true)['title'] ?? null) === $title);
    }
}
