<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Calculadora completa: reajuste anual do aporte e inflação média. */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            // Quanto o aporte mensal cresce por ano (% a.a.), ex: 5 = +5% ao ano.
            $table->decimal('independence_contribution_growth', 5, 2)->nullable();
            // Inflação média anual (% a.a.) — o custo de vida sobe com ela na projeção.
            $table->decimal('independence_inflation', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['independence_contribution_growth', 'independence_inflation']);
        });
    }
};
