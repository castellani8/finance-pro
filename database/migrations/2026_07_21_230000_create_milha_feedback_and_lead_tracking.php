<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feedback coletado pela Milha no painel (reclamações, sugestões, elogios,
     * bugs) e rastreabilidade das conversas da Milha vendedora na landing:
     * o que os leads perguntam, tokens gastos e cliques no CTA de cadastro.
     */
    public function up(): void
    {
        Schema::create('milha_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('user_id')->index();
            $table->string('tipo', 20); // reclamacao | sugestao | elogio | bug | outro
            $table->text('mensagem');
            $table->string('contexto')->nullable(); // tela/assunto que originou
            $table->timestamps();
        });

        Schema::create('milha_lead_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 100)->index();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->unsignedInteger('cta_clicks')->default(0);
            $table->timestamp('cta_first_clicked_at')->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->timestamps();
        });

        Schema::create('milha_lead_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained('milha_lead_conversations')
                ->cascadeOnDelete();
            $table->string('role', 12); // user | assistant
            $table->text('content');
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milha_lead_messages');
        Schema::dropIfExists('milha_lead_conversations');
        Schema::dropIfExists('milha_feedback');
    }
};
