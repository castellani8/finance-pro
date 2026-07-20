<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asset_price_history', function (Blueprint $table) {
            $table->id();
            $table->string('ticker');
            $table->date('date');
            $table->decimal('price_open', 10, 4);
            $table->decimal('price_close', 10, 4)->nullable();
            $table->decimal('price_high', 10, 4);
            $table->decimal('price_low', 10, 4);
            $table->string('source');

            $table->index(['ticker', 'date']);
            $table->unique(['ticker', 'date', 'source']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_price_histories');
    }
};
