<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Métricas materializadas do ativo (posição, investido, valor atual em
     * BRL), atualizadas por observer/refresher — eliminam o cálculo em PHP a
     * cada request no filtro de posição e na ordenação. Também cria o índice
     * de agregados do fluxo de caixa por tipo.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->decimal('position_quantity', 20, 6)->nullable();
            $table->decimal('invested_value', 15, 2)->nullable();
            $table->decimal('current_value', 15, 2)->nullable();
            $table->timestamp('metrics_refreshed_at')->nullable();

            $table->index(['tenant_id', 'position_quantity']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'type']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'position_quantity']);
            $table->dropColumn(['position_quantity', 'invested_value', 'current_value', 'metrics_refreshed_at']);
        });
    }
};
