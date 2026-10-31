<?php

namespace App\Reports;

use App\Models\Branch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What a report covers: business days from and to, and the branches. Only
 * branches the user works in are ever included.
 */
class ReportFilters
{
    /**
     * @param  list<int>  $branchIds
     */
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public array $branchIds,
    ) {}

    /**
     * From what was asked for, limited to what the user may see. No branch
     * means every branch they work in.
     */
    public static function for(User $user, ?string $from, ?string $to, ?int $branchId): self
    {
        $visible = Branch::visibleTo($user)->pluck('id')->all();

        $today = self::today($user);

        $from = self::date($from) ?? $today->startOfMonth();
        $to = self::date($to) ?? $today;

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return new self(
            $from,
            $to,
            $branchId !== null && in_array($branchId, $visible, true) ? [$branchId] : $visible,
        );
    }

    /**
     * Today's business day where the user works: by the branch's own
     * timezone and the time its day starts, not the server's calendar day.
     */
    public static function today(User $user, ?int $branchId = null): CarbonImmutable
    {
        $branch = Branch::visibleTo($user)->when($branchId, fn ($query, int $id) => $query->whereKey($id))->orderBy('id')->first();

        return $branch?->businessDateFor(now()) ?? CarbonImmutable::today();
    }

    private static function date(?string $value): ?CarbonImmutable
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $value)->startOfDay();
    }

    /**
     * @return Collection<int, Branch>
     */
    public function branches(): Collection
    {
        return Branch::withTrashed()->whereKey($this->branchIds)->orderBy('name')->get();
    }

    public function describe(): string
    {
        $branches = $this->branches();
        $where = $branches->count() === 1 ? $branches->first()->name : $branches->count().' branches';

        return $where.' · '.$this->from->format('j M Y').($this->from->eq($this->to) ? '' : ' to '.$this->to->format('j M Y'));
    }

    /**
     * @return list<string>
     */
    public function days(): array
    {
        $days = [];

        for ($day = $this->from; $day->lte($this->to); $day = $day->addDay()) {
            $days[] = $day->toDateString();
        }

        return $days;
    }
}
