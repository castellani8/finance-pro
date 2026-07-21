<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contratos recorrentes (aluguel mensal do trator, assinatura de software):
     * o scheduler materializa cada vencimento em uma transaction real.
     */
    public function up(): void
    {
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

            // INCOME (receita) ou EXPENSE (despesa)
            $table->string('type');
            $table->string('description');
            $table->string('category')->nullable();
            $table->decimal('amount', 15, 2);

            // Dia do vencimento (1-31; meses curtos usam o último dia do mês)
            $table->unsignedTinyInteger('day_of_month');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('active')->default(true);

            // Última data já materializada (cursor do gerador, permite catch-up)
            $table->date('last_generated_on')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_transactions');
    }
};
