<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Meta mensal de renda passiva por tenant (painel "viver de renda") e a
     * tabela padrão de notificações do Laravel/Filament (avisos de vencimento
     * de renda fixa e afins).
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->decimal('passive_income_goal', 15, 2)->nullable();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('passive_income_goal');
        });
    }
};
