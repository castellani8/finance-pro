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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            
            // Nome legível do ativo (ex: "Petrobras PN", "CDB Itaú 110% CDI", "Zupier SaaS")
            $table->string('name');
            
            // Categoria principal (ex: 'STOCK', 'FII', 'FIXED_INCOME', 'SOFTWARE', 'VEHICLE', 'REAL_ESTATE')
            $table->string('type');
            $table->string('ticker_or_code')->nullable()->index();
            
            // Detalhes flexíveis de Renda Fixa ou Metadados do Ativo em JSON
            // Ex para Renda Fixa: {"indexer": "CDI", "interest_rate": 110, "due_date": "2028-05-10"}
            $table->json('metadata')->nullable();// Moeda do ativo (padrão BRL)
            
            $table->string('currency', 3)->default('BRL');
            
            $table->foreignId('company_id')->nullable()->constrained('companies');
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
