<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cotações de câmbio (PTAX venda do Banco Central): permitem ativos e
     * contas em USD/EUR com conversão para BRL na valoração.
     */
    public function up(): void
    {
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->string('currency', 3);
            $table->date('date');
            $table->decimal('rate', 12, 6);

            $table->unique(['currency', 'date']);
            $table->timestamps();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->string('currency', 3)->default('BRL');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        Schema::dropIfExists('currency_rates');
    }
};
