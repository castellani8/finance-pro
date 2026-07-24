<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Plano de independência financeira ("Viver de Renda") do tenant. */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            // Custo de vida mensal que a renda passiva precisa cobrir.
            $table->decimal('independence_monthly_cost', 15, 2)->nullable();
            // Aporte mensal planejado daqui para frente.
            $table->decimal('independence_monthly_contribution', 15, 2)->nullable();
            // Retorno real anual esperado (% a.a.) usado na projeção.
            $table->decimal('independence_expected_return', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn([
                'independence_monthly_cost',
                'independence_monthly_contribution',
                'independence_expected_return',
            ]);
        });
    }
};
