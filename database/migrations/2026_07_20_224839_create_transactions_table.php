<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();

            // Tipo normalizado da operação: BUY, SELL, DIVIDEND, JCP, INCOME, INTEREST,
            // BONUS, SUBSCRIPTION, RIGHTS_CESSION, GROUPING, UPDATE, TRANSFER, CUSTODY_BLOCK
            $table->string('type');

            // Data em que a operação/compra aconteceu
            $table->date('transaction_date')->index();

            // Quantidade negociada (ex: 100 ações, 1.5 frações ou 1 para título inteiro)
            $table->decimal('quantity', 15, 6)->default(1);

            // Preço unitário na data da compra/venda (proventos/atualizações podem não ter)
            $table->decimal('unit_price', 15, 4)->nullable();

            // Valor total da transação (quantity * unit_price)
            $table->decimal('total_amount', 15, 4);

            // Taxas da B3, emolumentos ou corretagem pagos na operação
            $table->decimal('fees', 15, 4)->default(0);

            // Sentido do fluxo na origem (B3): 'Credito' (entrada) ou 'Debito' (saída)
            $table->string('direction')->nullable();

            // Texto original da coluna "Movimentação" da planilha da B3
            $table->string('movement')->nullable();

            // Instituição/corretora responsável pela custódia na operação
            $table->string('institution')->nullable();

            // Origem do registro: 'manual', 'b3', etc.
            $table->string('source')->default('manual');

            // Hash determinístico da linha importada, usado para upsert idempotente
            $table->string('external_hash')->nullable();

            // Observações ou anotações do usuário sobre essa transação específica
            $table->text('notes')->nullable();

            // Índice para consultas rápidas de extrato por data
            $table->index(['tenant_id', 'transaction_date']);

            // Deduplicação de importações: mesma linha da planilha não duplica
            $table->unique(['tenant_id', 'external_hash']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
