<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\IrReport;
use Illuminate\View\View;

/**
 * Relatório de IR somente-leitura para o contador. O acesso é por link
 * assinado temporário (middleware 'signed' na rota) gerado pelo dono da
 * carteira — expira sozinho e não dá acesso a mais nada do painel.
 */
class ContadorReportController extends Controller
{
    public function show(Tenant $tenant, int $year): View
    {
        return view('reports.ir-contador', [
            'tenant' => $tenant,
            'report' => app(IrReport::class)->build($tenant, $year),
        ]);
    }
}
