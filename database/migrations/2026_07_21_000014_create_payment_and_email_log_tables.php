<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Infra de pagamento (webhooks + campos de cobrança na assinatura) e o
     * log universal de e-mails enviados.
     */
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 30);
            $table->string('event', 60);
            $table->json('payload');
            $table->string('status', 20)->default('received');
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('from')->nullable();
            $table->string('to');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tag', 80)->nullable();
            $table->string('subject')->nullable();
            $table->longText('html_body')->nullable();
            $table->string('action_url', 2048)->nullable();
            $table->text('error_log')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('gateway', 30)->nullable();
            $table->string('billing_type', 20)->nullable();
            $table->string('latest_invoice_url', 2048)->nullable();
        });

        // Contas criadas antes da verificação de e-mail ser obrigatória são
        // consideradas verificadas — a exigência vale para novos cadastros.
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['gateway', 'billing_type', 'latest_invoice_url']);
        });

        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('webhook_logs');
    }
};
