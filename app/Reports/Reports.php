<?php

namespace App\Reports;

use App\Enums\KitchenStatus;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderDiscount;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Shift;
use App\Models\User;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Every report RasaHub offers, worked out live from the orders. Sales are
 * paid orders only, by the business day they were paid in, and use the
 * names, prices and tax each order kept. Refunds count on the business day
 * they were given.
 */
class Reports
{
    /**
     * The reports, their titles, and what they need.
     *
     * @return array<string, array{title: string, description: string, permission: Permission, feature: string|null}>
     */
    public static function catalogue(): array
    {
        $sales = Permission::ViewReports;

        return [
            'summary' => ['title' => 'Sales summary', 'description' => 'Sales, discounts, service charge, SST, refunds and takings, day by day.', 'permission' => $sales, 'feature' => null],
            'products' => ['title' => 'Sales by product', 'description' => 'What sold, how many and for how much, best sellers first.', 'permission' => $sales, 'feature' => null],
            'categories' => ['title' => 'Sales by category', 'description' => 'Sales by menu category.', 'permission' => $sales, 'feature' => null],
            'modifiers' => ['title' => 'Modifiers', 'description' => 'How often each option was chosen and what it added.', 'permission' => $sales, 'feature' => null],
            'hours' => ['title' => 'Sales by hour', 'description' => 'Busy hours of the day.', 'permission' => $sales, 'feature' => null],
            'staff' => ['title' => 'Sales by staff', 'description' => 'Orders and sales per staff member, with their discounts, voids and refunds.', 'permission' => $sales, 'feature' => null],
            'payments' => ['title' => 'Payments', 'description' => 'Takings by payment method, for reconciling.', 'permission' => $sales, 'feature' => null],
            'corrections' => ['title' => 'Discounts, voids and refunds', 'description' => 'Every one, with who did it, who approved it and why.', 'permission' => $sales, 'feature' => null],
            'sst' => ['title' => 'SST', 'description' => 'SST collected and the taxable amount, day by day, for the SST return.', 'permission' => $sales, 'feature' => null],
            'shifts' => ['title' => 'Shifts', 'description' => 'Each shift\'s float, takings, expected and counted cash.', 'permission' => Permission::ViewShiftReports, 'feature' => null],
            'branches' => ['title' => 'Branches compared', 'description' => 'Branches side by side.', 'permission' => $sales, 'feature' => 'branch_reports'],
            'group' => ['title' => 'Group report', 'description' => 'All branches as one, with each branch\'s share.', 'permission' => $sales, 'feature' => 'group_reporting'],
        ];
    }

    /**
     * The reports this user may run on their restaurant's plan.
     *
     * @return array<string, array{title: string, description: string, permission: Permission, feature: string|null}>
     */
    public static function available(User $user): array
    {
        $plan = app(TenantContext::class)->business()?->subscription?->plan;

        return array_filter(self::catalogue(), fn (array $report): bool => $user->can($report['permission'])
            && ($report['feature'] === null || ($plan?->includes($report['feature']) ?? false)));
    }

    public function run(string $report, ReportFilters $filters): ReportTable
    {
        return match ($report) {
            'summary' => $this->summary($filters),
            'products' => $this->products($filters),
            'categories' => $this->categories($filters),
            'modifiers' => $this->modifiers($filters),
            'hours' => $this->hours($filters),
            'staff' => $this->staff($filters),
            'payments' => $this->payments($filters),
            'corrections' => $this->corrections($filters),
            'sst' => $this->sst($filters),
            'shifts' => $this->shifts($filters),
            'branches' => $this->branches($filters),
            'group' => $this->group($filters),
        };
    }

    // ---------------------------------------------------------------------

    /**
     * @return Builder<Order>
     */
    private function paidOrders(ReportFilters $filters): Builder
    {
        return Order::where('status', OrderStatus::Completed)
            ->whereIn('branch_id', $filters->branchIds)
            ->whereBetween('business_date', [$filters->from->toDateString(), $filters->to->toDateString()]);
    }

    /**
     * Refunds given within the range, by the business day they were given at
     * their branch.
     *
     * @return Collection<int, Refund>
     */
    private function refunds(ReportFilters $filters): Collection
    {
        $branches = $filters->branches()->keyBy('id');

        return Refund::whereIn('branch_id', $filters->branchIds)
            ->whereBetween('created_at', [$filters->from->subDay(), $filters->to->addDays(2)])
            ->with('order')
            ->get()
            ->each(fn (Refund $refund) => $refund->setAttribute('business_day', $branches[$refund->branch_id]->businessDateFor($refund->created_at)->toDateString()))
            ->filter(fn (Refund $refund): bool => $refund->business_day >= $filters->from->toDateString() && $refund->business_day <= $filters->to->toDateString())
            ->values();
    }

    /**
     * The lines that were sold: of paid orders, not voided, with what each
     * one sold for after its own discounts. A set meal's parts count as the
     * products they are, for their extra charge.
     *
     * @return Collection<int, OrderItem>
     */
    private function soldLines(ReportFilters $filters): Collection
    {
        return OrderItem::whereIn('order_id', $this->paidOrders($filters)->select('id'))
            ->where('kitchen_status', '!=', KitchenStatus::Voided)
            ->get(['id', 'order_id', 'parent_id', 'product_id', 'name', 'quantity', 'refunded_quantity', 'line_total', 'discount_total']);
    }

    private function name(?int $userId): string
    {
        static $names = [];

        if ($userId === null) {
            return '—';
        }

        return $names[$userId] ??= User::acrossBusinesses()->find($userId)?->name ?? 'Removed user';
    }

    private function share(string $part, string $whole): float
    {
        return Money::isPositive($whole) ? round((float) $part / (float) $whole * 100, 1) : 0.0;
    }

    // ---------------------------------------------------------------------

    private function summary(ReportFilters $filters): ReportTable
    {
        $orders = $this->paidOrders($filters)->get(['business_date', 'guests', 'subtotal', 'discount_total', 'service_charge', 'sst', 'rounding', 'total'])->groupBy(fn (Order $order) => $order->business_date->toDateString());
        $refunds = $this->refunds($filters)->groupBy('business_day');

        $rows = [];

        foreach ($filters->days() as $day) {
            $dayOrders = $orders[$day] ?? collect();
            $refunded = Money::sum(($refunds[$day] ?? collect())->pluck('amount'));
            $total = Money::sum($dayOrders->pluck('total'));

            $rows[] = [
                'day' => $day,
                'orders' => $dayOrders->count(),
                'guests' => (int) $dayOrders->sum('guests'),
                'gross' => Money::sum($dayOrders->pluck('subtotal')),
                'discounts' => Money::sum($dayOrders->pluck('discount_total')),
                'service_charge' => Money::sum($dayOrders->pluck('service_charge')),
                'sst' => Money::sum($dayOrders->pluck('sst')),
                'rounding' => Money::sum($dayOrders->pluck('rounding')),
                'total' => $total,
                'refunds' => $refunded,
                'net' => bcsub($total, $refunded, 2),
            ];
        }

        $totals = $this->sumRows($rows, ['orders', 'guests', 'gross', 'discounts', 'service_charge', 'sst', 'rounding', 'total', 'refunds', 'net']);
        $totals['day'] = 'Total';

        $average = $totals['orders'] > 0 ? bcdiv($totals['total'], (string) $totals['orders'], 2) : Money::ZERO;

        return new ReportTable(
            'Sales summary',
            [
                ReportTable::column('day', 'Business day', 'date'),
                ReportTable::column('orders', 'Orders', 'int'),
                ReportTable::column('guests', 'Guests', 'int'),
                ReportTable::column('gross', 'Gross sales', 'money'),
                ReportTable::column('discounts', 'Discounts', 'money'),
                ReportTable::column('service_charge', 'Service charge', 'money'),
                ReportTable::column('sst', 'SST', 'money'),
                ReportTable::column('rounding', 'Rounding', 'money'),
                ReportTable::column('total', 'Takings', 'money'),
                ReportTable::column('refunds', 'Refunds', 'money'),
                ReportTable::column('net', 'Net takings', 'money'),
            ],
            $rows,
            $totals,
            ['type' => 'line', 'labels' => array_column($rows, 'day'), 'series' => ['Net takings' => array_map('floatval', array_column($rows, 'net'))]],
            [
                ['label' => 'Net takings', 'value' => $totals['net'], 'type' => 'money'],
                ['label' => 'Orders', 'value' => (string) $totals['orders'], 'type' => 'int'],
                ['label' => 'Average spend', 'value' => $average, 'type' => 'money'],
                ['label' => 'Refunds', 'value' => $totals['refunds'], 'type' => 'money'],
            ],
            'Gross sales are menu prices before discounts. Takings are what customers paid, with service charge, SST and rounding. Refunds count on the day they were given.',
        );
    }

    private function products(ReportFilters $filters): ReportTable
    {
        $lines = $this->soldLines($filters);
        $total = Money::sum($lines->map(fn (OrderItem $line): string => bcsub($line->line_total, $line->discount_total, 2)));

        $rows = $lines->groupBy(fn (OrderItem $line) => $line->product_id ?? 'name:'.$line->name)
            ->map(function (Collection $group) use ($total): array {
                $sales = Money::sum($group->map(fn (OrderItem $line): string => bcsub($line->line_total, $line->discount_total, 2)));

                return [
                    'product' => $group->first()->name,
                    'sold' => (int) $group->sum('quantity'),
                    'refunded' => (int) $group->sum('refunded_quantity'),
                    'in_sets' => (int) $group->whereNotNull('parent_id')->sum('quantity'),
                    'sales' => $sales,
                    'share' => $this->share($sales, $total),
                ];
            })
            ->sortByDesc(fn (array $row) => [(float) $row['sales'], $row['sold']])
            ->values()
            ->all();

        $totals = ['product' => 'Total', ...$this->sumRows($rows, ['sold', 'refunded', 'in_sets', 'sales']), 'share' => $rows === [] ? 0.0 : 100.0];

        return new ReportTable(
            'Sales by product',
            [
                ReportTable::column('product', 'Product'),
                ReportTable::column('sold', 'Sold', 'int'),
                ReportTable::column('in_sets', 'Of which in sets', 'int'),
                ReportTable::column('refunded', 'Refunded', 'int'),
                ReportTable::column('sales', 'Sales', 'money'),
                ReportTable::column('share', 'Share', 'percent'),
            ],
            $rows,
            $totals,
            ['type' => 'bar', 'labels' => array_slice(array_column($rows, 'product'), 0, 10), 'series' => ['Sales' => array_map('floatval', array_slice(array_column($rows, 'sales'), 0, 10))]],
            note: 'Sales are what each line sold for after its own discounts, before order discounts, service charge and SST. Set meal parts count as the products they are, for any extra charge.',
        );
    }

    private function categories(ReportFilters $filters): ReportTable
    {
        $lines = $this->soldLines($filters);
        $products = Product::withTrashed()->with(['category' => fn ($q) => $q->withTrashed()])->whereKey($lines->pluck('product_id')->filter()->unique())->get()->keyBy('id');
        $total = Money::sum($lines->map(fn (OrderItem $line): string => bcsub($line->line_total, $line->discount_total, 2)));

        $rows = $lines->groupBy(fn (OrderItem $line) => $products[$line->product_id]->category->name ?? 'No category')
            ->map(function (Collection $group, string $category) use ($total): array {
                $sales = Money::sum($group->map(fn (OrderItem $line): string => bcsub($line->line_total, $line->discount_total, 2)));

                return ['category' => $category, 'sold' => (int) $group->sum('quantity'), 'sales' => $sales, 'share' => $this->share($sales, $total)];
            })
            ->sortByDesc(fn (array $row) => (float) $row['sales'])
            ->values()
            ->all();

        return new ReportTable(
            'Sales by category',
            [
                ReportTable::column('category', 'Category'),
                ReportTable::column('sold', 'Items sold', 'int'),
                ReportTable::column('sales', 'Sales', 'money'),
                ReportTable::column('share', 'Share', 'percent'),
            ],
            $rows,
            ['category' => 'Total', ...$this->sumRows($rows, ['sold', 'sales']), 'share' => $rows === [] ? 0.0 : 100.0],
            ['type' => 'doughnut', 'labels' => array_column($rows, 'category'), 'series' => ['Sales' => array_map('floatval', array_column($rows, 'sales'))]],
            note: 'Each product is counted under the category it is in now.',
        );
    }

    private function modifiers(ReportFilters $filters): ReportTable
    {
        $lines = $this->soldLines($filters)->keyBy('id');

        $rows = OrderItemModifier::whereIn('order_item_id', $lines->keys())
            ->get(['order_item_id', 'group_name', 'name', 'price_delta'])
            ->groupBy(fn (OrderItemModifier $modifier) => $modifier->group_name.'|'.$modifier->name)
            ->map(function (Collection $group) use ($lines): array {
                $times = (int) $group->sum(fn (OrderItemModifier $modifier) => $lines[$modifier->order_item_id]->quantity);

                return [
                    'group' => $group->first()->group_name,
                    'option' => $group->first()->name,
                    'times' => $times,
                    'added' => Money::sum($group->map(fn (OrderItemModifier $modifier): string => bcmul($modifier->price_delta, (string) $lines[$modifier->order_item_id]->quantity, 2))),
                ];
            })
            ->sortBy([['group', 'asc'], ['times', 'desc']])
            ->values()
            ->all();

        return new ReportTable(
            'Modifiers',
            [
                ReportTable::column('group', 'Group'),
                ReportTable::column('option', 'Option'),
                ReportTable::column('times', 'Times chosen', 'int'),
                ReportTable::column('added', 'Added to sales', 'money'),
            ],
            $rows,
            ['group' => 'Total', 'option' => '', ...$this->sumRows($rows, ['times', 'added'])],
        );
    }

    private function hours(ReportFilters $filters): ReportTable
    {
        $branches = $filters->branches()->keyBy('id');
        $hours = array_fill(0, 24, ['orders' => 0, 'sales' => Money::ZERO]);

        foreach ($this->paidOrders($filters)->get(['branch_id', 'completed_at', 'total']) as $order) {
            $hour = (int) $order->completed_at->timezone($branches[$order->branch_id]->effectiveTimezone())->format('G');
            $hours[$hour]['orders']++;
            $hours[$hour]['sales'] = bcadd($hours[$hour]['sales'], $order->total, 2);
        }

        $rows = [];

        foreach ($hours as $hour => $figures) {
            if ($figures['orders'] === 0) {
                continue;
            }

            $rows[] = [
                'hour' => sprintf('%02d:00–%02d:59', $hour, $hour),
                'orders' => $figures['orders'],
                'sales' => $figures['sales'],
                'average' => bcdiv($figures['sales'], (string) $figures['orders'], 2),
            ];
        }

        return new ReportTable(
            'Sales by hour',
            [
                ReportTable::column('hour', 'Hour'),
                ReportTable::column('orders', 'Orders', 'int'),
                ReportTable::column('sales', 'Takings', 'money'),
                ReportTable::column('average', 'Average spend', 'money'),
            ],
            $rows,
            ['hour' => 'Total', ...$this->sumRows($rows, ['orders', 'sales']), 'average' => ''],
            ['type' => 'bar', 'labels' => array_column($rows, 'hour'), 'series' => ['Takings' => array_map('floatval', array_column($rows, 'sales'))]],
            note: 'By the hour each order was paid, in the branch\'s own time.',
        );
    }

    private function staff(ReportFilters $filters): ReportTable
    {
        $orders = $this->paidOrders($filters)->get(['id', 'opened_by', 'total']);
        $orderIds = $this->paidOrders($filters)->select('id');
        $people = [];

        $add = function (?int $userId, string $key, string|int $amount) use (&$people): void {
            if ($userId === null) {
                return;
            }

            $people[$userId] ??= ['name' => $this->name($userId), 'orders' => 0, 'sales' => Money::ZERO, 'discounts' => Money::ZERO, 'voids' => 0, 'refunds' => Money::ZERO];
            $people[$userId][$key] = is_int($amount) ? $people[$userId][$key] + $amount : bcadd($people[$userId][$key], $amount, 2);
        };

        foreach ($orders as $order) {
            $add($order->opened_by, 'orders', 1);
            $add($order->opened_by, 'sales', $order->total);
        }

        foreach (OrderDiscount::whereIn('order_id', $orderIds)->get(['applied_by', 'amount']) as $discount) {
            $add($discount->applied_by, 'discounts', $discount->amount);
        }

        foreach (OrderItem::whereIn('order_id', Order::whereIn('branch_id', $filters->branchIds)->whereBetween('business_date', [$filters->from->toDateString(), $filters->to->toDateString()])->select('id'))->whereNotNull('voided_by')->whereNull('parent_id')->get(['voided_by']) as $void) {
            $add($void->voided_by, 'voids', 1);
        }

        foreach ($this->refunds($filters) as $refund) {
            $add($refund->refunded_by, 'refunds', $refund->amount);
        }

        $rows = collect($people)->sortByDesc(fn (array $row) => (float) $row['sales'])->values()->all();

        return new ReportTable(
            'Sales by staff',
            [
                ReportTable::column('name', 'Staff member'),
                ReportTable::column('orders', 'Orders taken', 'int'),
                ReportTable::column('sales', 'Takings', 'money'),
                ReportTable::column('discounts', 'Discounts given', 'money'),
                ReportTable::column('voids', 'Lines voided', 'int'),
                ReportTable::column('refunds', 'Refunds given', 'money'),
            ],
            $rows,
            ['name' => 'Total', ...$this->sumRows($rows, ['orders', 'sales', 'discounts', 'voids', 'refunds'])],
            note: 'Orders count for the staff member who started them.',
        );
    }

    private function payments(ReportFilters $filters): ReportTable
    {
        $payments = Payment::whereIn('order_id', $this->paidOrders($filters)->select('id'))->get(['method_name', 'amount']);
        $total = Money::sum($payments->pluck('amount'));

        $rows = $payments->groupBy('method_name')
            ->map(fn (Collection $group, string $method): array => [
                'method' => $method,
                'payments' => $group->count(),
                'amount' => Money::sum($group->pluck('amount')),
                'share' => $this->share(Money::sum($group->pluck('amount')), $total),
            ])
            ->sortByDesc(fn (array $row) => (float) $row['amount'])
            ->values()
            ->all();

        $refunds = $this->refunds($filters);

        if ($refunds->isNotEmpty()) {
            $rows[] = ['method' => 'Cash refunds', 'payments' => $refunds->count(), 'amount' => '-'.Money::sum($refunds->pluck('amount')), 'share' => ''];
        }

        $net = bcsub($total, Money::sum($refunds->pluck('amount')), 2);

        return new ReportTable(
            'Payments',
            [
                ReportTable::column('method', 'Method'),
                ReportTable::column('payments', 'Payments', 'int'),
                ReportTable::column('amount', 'Amount', 'money'),
                ReportTable::column('share', 'Share', 'percent'),
            ],
            $rows,
            ['method' => 'Net takings', 'payments' => '', 'amount' => $net, 'share' => ''],
            ['type' => 'doughnut', 'labels' => $payments->groupBy('method_name')->keys()->all(), 'series' => ['Amount' => $payments->groupBy('method_name')->map(fn ($g) => (float) $g->sum('amount'))->values()->all()]],
            note: 'Payments for orders paid in the range. Card and QR amounts should match the card terminal and the bank.',
        );
    }

    private function corrections(ReportFilters $filters): ReportTable
    {
        $timezones = $filters->branches()->mapWithKeys(fn (Branch $branch) => [$branch->id => $branch->effectiveTimezone()]);
        $inRange = Order::whereIn('branch_id', $filters->branchIds)->whereBetween('business_date', [$filters->from->toDateString(), $filters->to->toDateString()]);
        $rows = [];

        foreach (OrderDiscount::whereIn('order_id', (clone $inRange)->where('status', OrderStatus::Completed)->select('id'))->with(['item'])->get() as $discount) {
            $order = Order::find($discount->order_id);
            $rows[] = ['when' => $discount->created_at->timezone($timezones[$order->branch_id])->format('Y-m-d H:i'), 'kind' => $discount->reason ? 'Discount (typed)' : 'Discount (preset)', 'order' => $order->number_label, 'detail' => $discount->label().($discount->item ? ' on '.$discount->item->name : ''), 'amount' => $discount->amount, 'by' => $this->name($discount->applied_by), 'approved' => $discount->approved_by ? $this->name($discount->approved_by) : '', 'reason' => $discount->reason ?? ''];
        }

        foreach (OrderItem::whereIn('order_id', (clone $inRange)->select('id'))->whereNotNull('voided_by')->whereNull('parent_id')->with('order')->get() as $void) {
            $rows[] = ['when' => $void->voided_at->timezone($timezones[$void->order->branch_id])->format('Y-m-d H:i'), 'kind' => 'Void', 'order' => $void->order->number_label, 'detail' => "{$void->quantity} × {$void->name}", 'amount' => $void->grossTotal(), 'by' => $this->name($void->voided_by), 'approved' => $void->void_approved_by ? $this->name($void->void_approved_by) : '', 'reason' => $void->void_reason ?? ''];
        }

        foreach ((clone $inRange)->where('status', OrderStatus::Cancelled)->whereNotNull('cancelled_by')->get() as $order) {
            $rows[] = ['when' => $order->cancelled_at->timezone($timezones[$order->branch_id])->format('Y-m-d H:i'), 'kind' => 'Cancelled order', 'order' => $order->number_label, 'detail' => '', 'amount' => '', 'by' => $this->name($order->cancelled_by), 'approved' => $order->cancel_approved_by ? $this->name($order->cancel_approved_by) : '', 'reason' => $order->cancel_reason ?? ''];
        }

        foreach ($this->refunds($filters) as $refund) {
            $rows[] = ['when' => $refund->created_at->timezone($timezones[$refund->branch_id])->format('Y-m-d H:i'), 'kind' => 'Refund', 'order' => $refund->order->number_label, 'detail' => $refund->number_label, 'amount' => $refund->amount, 'by' => $this->name($refund->refunded_by), 'approved' => $refund->approved_by ? $this->name($refund->approved_by) : '', 'reason' => $refund->reason];
        }

        usort($rows, fn (array $a, array $b): int => strcmp($b['when'], $a['when']));

        return new ReportTable(
            'Discounts, voids and refunds',
            [
                ReportTable::column('when', 'When', 'datetime'),
                ReportTable::column('kind', 'What'),
                ReportTable::column('order', 'Order'),
                ReportTable::column('detail', 'Detail'),
                ReportTable::column('amount', 'Amount', 'money'),
                ReportTable::column('by', 'By'),
                ReportTable::column('approved', 'Approved by'),
                ReportTable::column('reason', 'Reason'),
            ],
            $rows,
            highlights: [
                ['label' => 'Discounts', 'value' => Money::sum(collect($rows)->filter(fn ($r) => str_starts_with($r['kind'], 'Discount'))->pluck('amount')), 'type' => 'money'],
                ['label' => 'Voids', 'value' => (string) collect($rows)->where('kind', 'Void')->count(), 'type' => 'int'],
                ['label' => 'Cancelled orders', 'value' => (string) collect($rows)->where('kind', 'Cancelled order')->count(), 'type' => 'int'],
                ['label' => 'Refunds', 'value' => Money::sum(collect($rows)->where('kind', 'Refund')->pluck('amount')), 'type' => 'money'],
            ],
        );
    }

    private function sst(ReportFilters $filters): ReportTable
    {
        $orders = $this->paidOrders($filters)->get(['business_date', 'total', 'sst', 'rounding', 'sst_rate', 'sst_enabled'])->groupBy(fn (Order $order) => $order->business_date->toDateString());
        $rows = [];

        foreach ($filters->days() as $day) {
            $dayOrders = ($orders[$day] ?? collect())->where('sst_enabled', true);
            $sst = Money::sum($dayOrders->pluck('sst'));
            // What SST was charged on: takings without SST and rounding.
            $taxable = Money::sum($dayOrders->map(fn (Order $order): string => bcsub(bcsub($order->total, $order->sst, 2), $order->rounding, 2)));

            $rows[] = ['day' => $day, 'orders' => $dayOrders->count(), 'taxable' => $taxable, 'sst' => $sst, 'rates' => $dayOrders->pluck('sst_rate')->unique()->map(fn ($rate) => rtrim(rtrim($rate, '0'), '.').'%')->implode(', ')];
        }

        $totals = ['day' => 'Total', ...$this->sumRows($rows, ['orders', 'taxable', 'sst']), 'rates' => ''];

        return new ReportTable(
            'SST',
            [
                ReportTable::column('day', 'Business day', 'date'),
                ReportTable::column('orders', 'Orders with SST', 'int'),
                ReportTable::column('taxable', 'Taxable amount', 'money'),
                ReportTable::column('sst', 'SST collected', 'money'),
                ReportTable::column('rates', 'Rate'),
            ],
            $rows,
            $totals,
            highlights: [
                ['label' => 'SST collected', 'value' => $totals['sst'], 'type' => 'money'],
                ['label' => 'Taxable amount', 'value' => $totals['taxable'], 'type' => 'money'],
            ],
            note: 'Paid orders only, at the rate each order was sold under. Refunds are not taken off here; adjust for them on the return as your accountant advises.',
        );
    }

    private function shifts(ReportFilters $filters): ReportTable
    {
        $rows = Shift::whereIn('branch_id', $filters->branchIds)
            ->whereBetween('opened_at', [$filters->from->subDay(), $filters->to->addDays(2)])
            ->with(['terminal', 'branch'])
            ->orderBy('opened_at')
            ->get()
            ->filter(fn (Shift $shift): bool => in_array($shift->branch->businessDateFor($shift->opened_at)->toDateString(), $filters->days(), true))
            ->map(function (Shift $shift): array {
                $timezone = $shift->branch->effectiveTimezone();
                $expected = $shift->expected_cash ?? $shift->expectedCash();

                return [
                    'terminal' => $shift->terminal->name.' · '.$shift->branch->name,
                    'opened' => $shift->opened_at->timezone($timezone)->format('Y-m-d H:i'),
                    'closed' => $shift->closed_at?->timezone($timezone)->format('Y-m-d H:i') ?? 'Open',
                    'by' => $this->name($shift->opened_by),
                    'float' => $shift->opening_float,
                    'taken' => Money::sum($shift->payments()->pluck('amount')),
                    'expected' => $expected,
                    'counted' => $shift->counted_cash ?? '',
                    'difference' => $shift->variance ?? '',
                ];
            })
            ->values()
            ->all();

        return new ReportTable(
            'Shifts',
            [
                ReportTable::column('terminal', 'Terminal'),
                ReportTable::column('opened', 'Opened', 'datetime'),
                ReportTable::column('closed', 'Closed', 'datetime'),
                ReportTable::column('by', 'Opened by'),
                ReportTable::column('float', 'Float', 'money'),
                ReportTable::column('taken', 'Taken (all methods)', 'money'),
                ReportTable::column('expected', 'Cash expected', 'money'),
                ReportTable::column('counted', 'Cash counted', 'money'),
                ReportTable::column('difference', 'Difference', 'money'),
            ],
            $rows,
            note: 'Cash expected is the float plus cash taken, less cash refunded.',
        );
    }

    private function branches(ReportFilters $filters): ReportTable
    {
        $orders = $this->paidOrders($filters)->get(['id', 'branch_id', 'total', 'guests'])->groupBy('branch_id');
        $refunds = $this->refunds($filters)->groupBy('branch_id');
        $lines = $this->soldLines($filters)->load('order:id,branch_id');

        $rows = $filters->branches()->map(function (Branch $branch) use ($orders, $refunds, $lines): array {
            $branchOrders = $orders[$branch->id] ?? collect();
            $total = Money::sum($branchOrders->pluck('total'));
            $refunded = Money::sum(($refunds[$branch->id] ?? collect())->pluck('amount'));
            $best = $lines->filter(fn (OrderItem $line) => $line->order->branch_id === $branch->id)->groupBy('name')->sortByDesc(fn ($g) => $g->sum('quantity'))->keys()->first();

            return [
                'branch' => $branch->name,
                'orders' => $branchOrders->count(),
                'guests' => (int) $branchOrders->sum('guests'),
                'total' => $total,
                'average' => $branchOrders->isNotEmpty() ? bcdiv($total, (string) $branchOrders->count(), 2) : Money::ZERO,
                'refunds' => $refunded,
                'net' => bcsub($total, $refunded, 2),
                'best' => $best ?? '—',
            ];
        })->sortByDesc(fn (array $row) => (float) $row['net'])->values()->all();

        return new ReportTable(
            'Branches compared',
            [
                ReportTable::column('branch', 'Branch'),
                ReportTable::column('orders', 'Orders', 'int'),
                ReportTable::column('guests', 'Guests', 'int'),
                ReportTable::column('total', 'Takings', 'money'),
                ReportTable::column('average', 'Average spend', 'money'),
                ReportTable::column('refunds', 'Refunds', 'money'),
                ReportTable::column('net', 'Net takings', 'money'),
                ReportTable::column('best', 'Best seller'),
            ],
            $rows,
            ['branch' => 'Total', ...$this->sumRows($rows, ['orders', 'guests', 'total', 'refunds', 'net']), 'average' => '', 'best' => ''],
            ['type' => 'bar', 'labels' => array_column($rows, 'branch'), 'series' => ['Net takings' => array_map('floatval', array_column($rows, 'net'))]],
        );
    }

    private function group(ReportFilters $filters): ReportTable
    {
        $branches = $this->branches($filters);
        $summary = $this->summary($filters);
        $total = $branches->totals['net'] ?? Money::ZERO;

        $rows = array_map(fn (array $row): array => [
            'rank' => 0,
            'branch' => $row['branch'],
            'net' => $row['net'],
            'share' => $this->share($row['net'], $total),
            'orders' => $row['orders'],
            'average' => $row['average'],
        ], $branches->rows);

        foreach ($rows as $index => $row) {
            $rows[$index]['rank'] = $index + 1;
        }

        return new ReportTable(
            'Group report',
            [
                ReportTable::column('rank', 'Rank', 'int'),
                ReportTable::column('branch', 'Branch'),
                ReportTable::column('net', 'Net takings', 'money'),
                ReportTable::column('share', 'Share of group', 'percent'),
                ReportTable::column('orders', 'Orders', 'int'),
                ReportTable::column('average', 'Average spend', 'money'),
            ],
            $rows,
            ['rank' => '', 'branch' => 'Group total', 'net' => $total, 'share' => $rows === [] ? 0.0 : 100.0, 'orders' => $branches->totals['orders'] ?? 0, 'average' => ''],
            $summary->chart,
            $summary->highlights,
            'Every branch as one group: the trend is the group\'s net takings day by day.',
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function sumRows(array $rows, array $keys): array
    {
        $totals = [];

        foreach ($keys as $key) {
            $values = array_column($rows, $key);
            $totals[$key] = $values !== [] && is_int($values[0]) ? array_sum($values) : Money::sum(array_map('strval', $values));
        }

        return $totals;
    }
}
