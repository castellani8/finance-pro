<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo dos tickers negociáveis na B3 (sincronizado da brapi), usado
     * pelo formulário de ativos para o usuário ESCOLHER o ticker em vez de
     * digitar de cabeça.
     */
    public function up(): void
    {
        Schema::create('b3_listed_tickers', function (Blueprint $table) {
            $table->id();
            $table->string('ticker')->unique();
            $table->string('name')->nullable();
            // Classificação da brapi: stock, fund (FII), bdr...
            $table->string('asset_kind')->nullable()->index();
            $table->string('sector')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b3_listed_tickers');
    }
};
