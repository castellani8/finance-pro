<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Carta de patrimônio (documento de sucessão): versão imprimível de onde está
 * cada ativo e conta da carteira, para a família saber a quem recorrer.
 * Restrita a usuários autenticados que pertencem ao tenant.
 */
class CartaPatrimonioController extends Controller
{
    public function show(Request $request, Tenant $tenant): View
    {
        abort_unless($request->user()?->canAccessTenant($tenant), 403);

        $assets = Asset::query()
            ->where('tenant_id', $tenant->getKey())
            ->wherePositionPositive()
            ->with('transactions')
            ->orderBy('name')
            ->get();

        Asset::primeMarketData($assets);

        $accounts = Account::query()
            ->where('tenant_id', $tenant->getKey())
            ->with('transactions')
            ->orderBy('name')
            ->get();

        return view('reports.carta-patrimonio', [
            'tenant' => $tenant,
            'members' => $tenant->users()->orderBy('name')->get(),
            'companies' => Company::query()->where('tenant_id', $tenant->getKey())->orderBy('name')->get(),
            'accounts' => $accounts,
            // Agrupado pelos rótulos em português usados no app inteiro.
            'assetsByType' => $assets->groupBy(fn (Asset $asset): string => Asset::TYPE_LABELS[$asset->type] ?? $asset->type),
        ]);
    }
}
