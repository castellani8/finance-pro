<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O documento (CNPJ/CPF) era unique global — dois tenants não podiam ter
     * a mesma empresa. Passa a ser unique por tenant e opcional.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['document']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('document')->nullable()->change();
            $table->unique(['tenant_id', 'document']);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'document']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('document')->nullable(false)->change();
            $table->unique('document');
        });
    }
};
