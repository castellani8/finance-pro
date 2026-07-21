<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contas (banco, corretora, caixa): o dinheiro passa a ter onde morar.
     * Lançamentos ligados a uma conta movem o saldo, e o saldo entra no
     * patrimônio total — uma despesa paga pela conta reduz o patrimônio.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('name');
            // bank | broker | cash | other
            $table->string('kind')->default('bank');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
        });

        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });

        Schema::dropIfExists('accounts');
    }
};
