<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A coluna "data" precisa ser json/jsonb no PostgreSQL para o Filament
     * conseguir filtrar com "data"->>'format'. Em SQLite/MySQL o tipo text
     * funcionava por acidente; em bancos onde a tabela já nasceu como json
     * (migration original corrigida) esta conversão é um no-op.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE jsonb USING data::jsonb');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
    }
};
