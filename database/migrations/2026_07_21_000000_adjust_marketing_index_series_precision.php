<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O SGS publica o CDI/SELIC diários com 6 casas decimais (ex: 0,052531);
     * decimal(10,4) truncava para 0,0525 e acumulava drift na correção.
     * annual_rate vira nullable (recebia uma cópia do daily_factor como hack)
     * e as linhas de IBOV são removidas: a série 7832 não é uma taxa como as
     * demais e nunca deve alimentar o IndexAccumulator.
     */
    public function up(): void
    {
        Schema::table('marketing_index_series', function (Blueprint $table) {
            $table->decimal('daily_factor', 12, 8)->change();
            $table->decimal('annual_rate', 12, 8)->nullable()->change();
        });

        DB::table('marketing_index_series')->where('index_code', 'IBOV')->delete();
    }

    public function down(): void
    {
        Schema::table('marketing_index_series', function (Blueprint $table) {
            $table->decimal('daily_factor', 10, 4)->change();
            $table->decimal('annual_rate', 10, 4)->nullable(false)->change();
        });
    }
};
