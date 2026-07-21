<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Infra de e-mail marketing: opt-out por usuário e registro de cada
     * campanha enviada (a unique impede reenvio da mesma campanha).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('marketing_emails_unsubscribed_at')->nullable();
        });

        Schema::create('marketing_email_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('campaign', 50);
            $table->timestamp('sent_at');
            $table->unique(['user_id', 'campaign']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_email_sends');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('marketing_emails_unsubscribed_at');
        });
    }
};
