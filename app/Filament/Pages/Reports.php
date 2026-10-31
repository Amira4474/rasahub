<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Reports\ReportFilters;
use App\Reports\Reports as ReportCatalogue;
use App\Reports\ReportTable;
use App\Tenancy\TenantContext;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * The restaurant's reports: pick one, a range of business days and a
 * branch; see it as figures, a chart and a table; download it as Excel or
 * PDF.
 */
class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.reports';

    // Reports can be wide, so the page uses the whole width.
    protected Width|string|null $maxContentWidth = Width::Full;

    #[Url]
    public string $report = 'summary';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public ?int $branch = null;

    public static function canAccess(): bool
    {
        return app(TenantContext::class)->business() !== null && ReportCatalogue::available(auth()->user()) !== [];
    }

    public function mount(): void
    {
        $available = $this->available();

        if (! array_key_exists($this->report, $available)) {
            $this->report = array_key_first($available);
        }

        $today = ReportFilters::today(auth()->user(), $this->branch);

        $this->from = $this->from ?: $today->startOfMonth()->toDateString();
        $this->to = $this->to ?: $today->toDateString();
    }

    /**
     * @return array<string, array{title: string, description: string}>
     */
    public function available(): array
    {
        return ReportCatalogue::available(auth()->user());
    }

    #[Computed]
    public function filters(): ReportFilters
    {
        return ReportFilters::for(auth()->user(), $this->from, $this->to, $this->branch);
    }

    #[Computed]
    public function table(): ReportTable
    {
        abort_unless(array_key_exists($this->report, $this->available()), 403);

        return app(ReportCatalogue::class)->run($this->report, $this->filters);
    }

    /**
     * @return Collection<int, Branch>
     */
    #[Computed]
    public function branches(): Collection
    {
        return Branch::visibleTo(auth()->user())->orderBy('name')->get(['id', 'name']);
    }

    public function show(string $report): void
    {
        if (array_key_exists($report, $this->available())) {
            $this->report = $report;
        }
    }

    /**
     * A ready-made range: today, yesterday, this week, this month, last month.
     */
    public function range(string $range): void
    {
        $today = ReportFilters::today(auth()->user(), $this->branch);

        [$from, $to] = match ($range) {
            'yesterday' => [$today->subDay(), $today->subDay()],
            'week' => [$today->startOfWeek(), $today],
            'month' => [$today->startOfMonth(), $today],
            'last-month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            default => [$today, $today],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
    }

    public function exportUrl(string $format): string
    {
        return route('reports.export', ['report' => $this->report, 'format' => $format, 'from' => $this->filters->from->toDateString(), 'to' => $this->filters->to->toDateString(), 'branch' => $this->branch]);
    }

    public function currency(): string
    {
        return app(TenantContext::class)->business()->currency;
    }
}
