<?php

namespace App\Console\Commands;

use App\Services\RecurringTransactionGenerator;
use Illuminate\Console\Command;

class GenerateRecurringTransactions extends Command
{
    protected $signature = 'ledger:generate-recurring';

    protected $description = 'Materializa os contratos recorrentes vencidos em lançamentos (todos os tenants)';

    public function handle(RecurringTransactionGenerator $generator): int
    {
        $created = $generator->generateDue();

        $this->info("{$created} lançamento(s) recorrente(s) gerado(s).");

        return self::SUCCESS;
    }
}
