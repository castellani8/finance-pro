<?php

namespace Tests\Feature;

use App\Models\RecurringTransaction;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\RecurringTransactionGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecurringTransactionGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-21');

        $this->tenant = new Tenant;
        $this->tenant->forceFill(['name' => 'Tenant de Teste', 'uuid' => (string) Str::uuid()])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_gera_vencidos_com_catch_up_e_nao_duplica_em_reexecucao(): void
    {
        $contract = $this->makeContract([
            'type' => 'INCOME',
            'description' => 'Aluguel do trator',
            'amount' => 3000,
            'day_of_month' => 5,
            'starts_on' => '2026-04-01',
        ]);

        $created = app(RecurringTransactionGenerator::class)->generateDue($this->tenant);

        // Vencimentos: 05/04, 05/05, 05/06, 05/07.
        $this->assertSame(4, $created);
        $this->assertSame(4, Transaction::where('recurring_transaction_id', $contract->getKey())->count());
        $this->assertSame('2026-07-05', substr((string) $contract->fresh()->getRawOriginal('last_generated_on'), 0, 10));

        $first = Transaction::where('recurring_transaction_id', $contract->getKey())->orderBy('transaction_date')->first();
        $this->assertSame('INCOME', $first->type);
        $this->assertSame('Credito', $first->direction);
        $this->assertSame('recurring', $first->source);
        $this->assertSame('Aluguel do trator', $first->movement);

        // Reexecutar não duplica.
        $this->assertSame(0, app(RecurringTransactionGenerator::class)->generateDue($this->tenant));
        $this->assertSame(4, Transaction::count());
    }

    public function test_dia_31_cai_no_ultimo_dia_de_meses_curtos_e_despesa_sai_como_debito(): void
    {
        $contract = $this->makeContract([
            'type' => 'EXPENSE',
            'description' => 'Assinatura Claude Code',
            'amount' => 100,
            'day_of_month' => 31,
            'starts_on' => '2026-01-31',
        ]);

        app(RecurringTransactionGenerator::class)->generateDue($this->tenant);

        $dates = Transaction::where('recurring_transaction_id', $contract->getKey())
            ->orderBy('transaction_date')
            ->pluck('transaction_date')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31', '2026-06-30'], $dates);
        $this->assertSame('Debito', Transaction::first()->direction);
    }

    public function test_respeita_fim_de_contrato_e_inatividade(): void
    {
        $this->makeContract([
            'type' => 'INCOME',
            'description' => 'Contrato encerrado',
            'amount' => 500,
            'day_of_month' => 10,
            'starts_on' => '2026-03-01',
            'ends_on' => '2026-04-30',
        ]);
        $this->makeContract([
            'type' => 'INCOME',
            'description' => 'Contrato pausado',
            'amount' => 500,
            'day_of_month' => 10,
            'starts_on' => '2026-01-01',
            'active' => false,
        ]);

        $created = app(RecurringTransactionGenerator::class)->generateDue($this->tenant);

        // Só o encerrado gera, e só até o fim: 10/03 e 10/04.
        $this->assertSame(2, $created);
        $this->assertSame(2, Transaction::count());
    }

    private function makeContract(array $attributes): RecurringTransaction
    {
        return RecurringTransaction::create(array_merge([
            'tenant_id' => $this->tenant->getKey(),
            'active' => true,
        ], $attributes));
    }
}
