<?php

namespace App\Filament\Pages;

use App\Services\UpcomingEvents;
use App\Support\PortfolioCache;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class AgendaDoMes extends Page
{
    protected string $view = 'filament.pages.agenda-do-mes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Agenda do Mês';

    protected static ?string $title = 'Agenda do Mês';

    protected static ?int $navigationSort = 47;

    /** Quantos meses à frente dá para navegar (estimativa degrada além disso). */
    private const MAX_MONTHS_AHEAD = 6;

    /** Mês exibido, no formato Y-m. */
    public string $month = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function previousMonth(): void
    {
        if (! $this->isFirstMonth()) {
            $this->month = Carbon::createFromFormat('Y-m-d', $this->month.'-01')->subMonth()->format('Y-m');
        }
    }

    public function nextMonth(): void
    {
        if (! $this->isLastMonth()) {
            $this->month = Carbon::createFromFormat('Y-m-d', $this->month.'-01')->addMonth()->format('Y-m');
        }
    }

    public function isFirstMonth(): bool
    {
        return $this->month <= now()->format('Y-m');
    }

    public function isLastMonth(): bool
    {
        return $this->month >= now()->addMonthsNoOverflow(self::MAX_MONTHS_AHEAD)->format('Y-m');
    }

    public function getMonthLabel(): string
    {
        return str(Carbon::createFromFormat('Y-m-d', $this->month.'-01')
            ->locale('pt_BR')
            ->translatedFormat('F \d\e Y'))->ucfirst();
    }

    /** @return array{events: array<int, array<string, mixed>>, totals: array<string, float>} */
    public function getAgenda(): array
    {
        $tenant = Filament::getTenant();

        return PortfolioCache::remember(
            $tenant->getKey(),
            "agenda.{$this->month}",
            fn (): array => app(UpcomingEvents::class)->month($tenant, $this->month),
        );
    }
}
