<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assinatura única por usuário (plano único). Campos asaas_* ficam
     * prontos para a integração de cobrança; até lá o ciclo é trial → expired
     * controlado localmente. Usuários existentes ganham trial contado a
     * partir do próprio cadastro.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('trialing');
            $table->timestamp('trial_ends_at');
            $table->timestamp('current_period_ends_at')->nullable();
            $table->decimal('price', 8, 2);
            $table->timestamp('canceled_at')->nullable();
            $table->string('asaas_customer_id')->nullable();
            $table->string('asaas_subscription_id')->nullable();
            $table->timestamps();
        });

        $trialDays = (int) config('landing.plan.trial_days', 15);
        $price = (float) str_replace(',', '.', (string) config('landing.plan.price', '19,90'));
        $now = now();

        foreach (DB::table('users')->get(['id', 'created_at']) as $user) {
            DB::table('subscriptions')->insert([
                'user_id' => $user->id,
                'status' => 'trialing',
                'trial_ends_at' => Carbon::parse($user->created_at)->addDays($trialDays),
                'price' => $price,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
