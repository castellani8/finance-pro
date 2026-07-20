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
        Schema::create('marketing_index_series', function (Blueprint $table) {
            $table->id();
            $table->string('index_code');
            $table->date('date');
            $table->decimal('daily_factor', 10, 4);
            $table->decimal('annual_rate', 10, 4);

            $table->index(['index_code', 'date']);
            $table->unique(['index_code', 'date']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_index_series');
    }
};
