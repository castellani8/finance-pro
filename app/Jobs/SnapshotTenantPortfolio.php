<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\PortfolioEvolution;
use App\Support\PortfolioCache;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fotografa a carteira de UM tenant (isolamento: a falha de um tenant não
 * derruba os demais e cada um vira um job com retry próprio).
 */
class SnapshotTenantPortfolio implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public Tenant $tenant,
        public int $days = 1,
        public bool $rebuild = false,
    ) {}

    public function handle(PortfolioEvolution $evolution): void
    {
        $evolution->dailySeries($this->tenant, $this->days, $this->rebuild);

        PortfolioCache::bump($this->tenant->getKey());
    }
}
