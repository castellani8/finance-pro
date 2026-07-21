<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lançamentos avulsos: dinheiro que entra/sai sem estar ligado a um ativo
     * (ex: assinatura de software paga pela empresa). asset_id vira opcional e
     * o lançamento pode apontar para uma empresa e ter categoria; lançamentos
     * gerados por contratos recorrentes guardam o vínculo com o contrato.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('asset_id')->nullable()->change();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('category')->nullable();
            $table->foreignId('recurring_transaction_id')->nullable()->constrained('recurring_transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('recurring_transaction_id');
            $table->dropColumn('category');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('asset_id')->nullable(false)->change();
        });
    }
};
