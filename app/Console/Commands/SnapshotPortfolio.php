<?php

namespace App\Console\Commands;

use App\Jobs\SnapshotTenantPortfolio;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SnapshotPortfolio extends Command
{
    protected $signature = 'portfolio:snapshot
                            {--days=1 : Quantos dias para trás fotografar (inclui hoje)}
                            {--rebuild : Recalcula mesmo os dias já fotografados}
                            {--sync : Executa na hora, sem passar pela fila}';

    protected $description = 'Fotografa a carteira de cada tenant (um job de fila por tenant)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $rebuild = (bool) $this->option('rebuild');

        foreach (Tenant::all() as $tenant) {
            $job = new SnapshotTenantPortfolio($tenant, $days, $rebuild);

            if ($this->option('sync')) {
                dispatch_sync($job);
                $this->info("{$tenant->name}: fotografado.");
            } else {
                dispatch($job);
                $this->info("{$tenant->name}: job enfileirado.");
            }
        }

        return self::SUCCESS;
    }
}
