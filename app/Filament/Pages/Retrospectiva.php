<?php

namespace App\Filament\Pages;

use App\Models\Transaction;
use App\Services\YearInReview;
use App\Support\PortfolioCache;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Retrospectiva extends Page
{
    protected string $view = 'filament.pages.retrospectiva';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|\UnitEnum|null $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Retrospectiva';

    protected static ?string $title = 'Retrospectiva do ano';

    protected static ?int $navigationSort = 52;

    public ?int $year = null;

    public function mount(): void
    {
        $options = $this->getYearOptions();
        $this->year = $options[0] ?? now()->year;
    }

    /** @return array<int, int> */
    public function getYearOptions(): array
    {
        return Transaction::query()
            ->where('tenant_id', Filament::getTenant()->getKey())
            // cast p/ text: no PostgreSQL substr() não aceita coluna date.
            ->selectRaw('distinct substr(cast(transaction_date as text), 1, 4) as year')
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($y): int => (int) $y)
            ->all();
    }

    /** @return array<string, mixed> */
    public function getReview(): array
    {
        $tenant = Filament::getTenant();

        return PortfolioCache::remember(
            $tenant->getKey(),
            "year-review.{$this->year}",
            fn (): array => app(YearInReview::class)->build($tenant, (int) $this->year),
        );
    }
}
