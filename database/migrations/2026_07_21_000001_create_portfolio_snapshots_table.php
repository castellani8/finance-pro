<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fotografia diária da carteira por tenant (valor investido e de mercado),
     * alimentada pelo command portfolio:snapshot e usada na evolução diária.
     */
    public function up(): void
    {
        Schema::create('portfolio_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('invested', 15, 2);
            $table->decimal('current_value', 15, 2);

            $table->unique(['tenant_id', 'date']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_snapshots');
    }
};
