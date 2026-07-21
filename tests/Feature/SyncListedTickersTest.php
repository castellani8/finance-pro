<?php

namespace Tests\Feature;

use App\Models\B3ListedTicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncListedTickersTest extends TestCase
{
    use RefreshDatabase;

    public function test_sincroniza_o_catalogo_de_tickers_da_brapi(): void
    {
        Http::fake([
            'brapi.dev/api/quote/list*' => Http::response([
                'stocks' => [
                    ['stock' => 'PETR4', 'name' => 'PETROBRAS PN', 'type' => 'stock', 'sector' => 'Energy'],
                    ['stock' => 'KNCR11', 'name' => 'KINEA RENDIMENTOS', 'type' => 'fund', 'sector' => null],
                    ['stock' => '', 'name' => 'invalido'],
                ],
            ]),
        ]);

        $this->artisan('marketing:sync-tickers')->assertSuccessful();

        $this->assertSame(2, B3ListedTicker::count());
        $this->assertSame('PETR4 — PETROBRAS PN', B3ListedTicker::where('ticker', 'PETR4')->first()->label());

        // Rodar de novo atualiza em vez de duplicar.
        $this->artisan('marketing:sync-tickers')->assertSuccessful();
        $this->assertSame(2, B3ListedTicker::count());
    }
}
