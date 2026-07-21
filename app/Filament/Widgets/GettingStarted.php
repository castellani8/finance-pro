<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Guia de primeiros passos, exibido no dashboard só enquanto a carteira do
 * tenant estiver vazia — leva o usuário direto ao momento "uau" da importação.
 */
class GettingStarted extends Widget
{
    protected string $view = 'filament.widgets.getting-started';

    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $tenant = Filament::getTenant();

        return $tenant !== null
            && ! Transaction::where('tenant_id', $tenant->getKey())->exists();
    }
}
