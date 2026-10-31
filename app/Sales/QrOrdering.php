<?php

namespace App\Sales;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Branch;
use App\Models\Business;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantSetting;
use App\Models\TableRequest;
use App\Support\Money;
use App\Support\Sst;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;

/**
 * Ordering from a table by QR code: finding the table from its link,
 * placing a customer's order for staff to confirm, and calls for staff or
 * the bill. Prices and choices are checked by the till, as at the POS.
 */
class QrOrdering
{
    public const MAX_LINES = 30;

    public const MAX_QUANTITY = 20;

    public function __construct(private readonly Till $till) {}

    /**
     * The business a QR link belongs to, if it may take QR orders: active,
     * subscribed, and on a plan with QR ordering.
     */
    public static function business(string $acronym): ?Business
    {
        $business = Business::where('acronym', strtoupper($acronym))->where('is_active', true)->first();

        return $business !== null
            && $business->accessProblem() === null
            && ($business->subscription?->plan->includes('qr_ordering') ?? false)
            ? $business
            : null;
    }

    /**
     * The table a QR link points to, inside the business being acted in, if
     * it is taking orders.
     */
    public static function table(string $token): ?DiningTable
    {
        $table = DiningTable::where('qr_token', $token)->where('is_active', true)->with('area.branch')->first();

        return $table !== null
            && $table->area?->is_active
            && $table->area->deleted_at === null
            && $table->area->branch?->is_active
            && $table->area->branch->qr_ordering_enabled
            ? $table
            : null;
    }

    /**
     * Place a customer's order at the table, waiting for staff, or straight
     * to the kitchen if the branch confirms QR orders by itself.
     *
     * @param  list<array{product_id: int, quantity: int, modifiers?: list<int>, parts?: array<int, list<int>>, note?: ?string}>  $lines
     */
    public function place(DiningTable $table, array $lines, ?string $name = null, ?string $phone = null, bool $consent = false): Order
    {
        if ($lines === []) {
            throw new SaleException('Your basket is empty.');
        }

        if (count($lines) > self::MAX_LINES) {
            throw new SaleException('That is too many items for one order. Please send part of it first.');
        }

        $branch = $table->area->branch;

        $order = DB::connection((new Order)->getConnectionName())->transaction(function () use ($table, $branch, $lines, $name, $phone, $consent): Order {
            $order = $this->till->createOrder($branch, null, null, OrderType::DineIn, $table, source: 'qr', status: OrderStatus::Pending);

            foreach ($lines as $line) {
                $quantity = (int) ($line['quantity'] ?? 1);

                if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
                    throw new SaleException('Each item can be ordered 1 to '.self::MAX_QUANTITY.' times.');
                }

                $product = Product::find($line['product_id'] ?? 0) ?? throw new SaleException('Something in your basket is no longer on the menu.');

                $this->till->addLine($order, $product, $quantity, array_map('intval', $line['modifiers'] ?? []), $line['parts'] ?? [], isset($line['note']) ? mb_substr((string) $line['note'], 0, 200) : null);
            }

            if (filled($phone)) {
                $order->update(['customer_id' => $this->till->customerFor($phone, $name, $consent)->id]);
            } elseif (filled($name)) {
                $order->update(['note' => 'Name: '.mb_substr($name, 0, 100)]);
            }

            return $order;
        });

        if ($branch->qr_auto_confirm) {
            return $this->till->confirmQrOrder($order, null);
        }

        return $order->refresh();
    }

    /**
     * A call for staff, or for the bill. One open call of each kind per table
     * is enough.
     */
    public function request(DiningTable $table, string $type): TableRequest
    {
        return TableRequest::firstOrCreate(
            ['dining_table_id' => $table->id, 'type' => $type, 'resolved_at' => null],
            ['branch_id' => $table->area->branch_id],
        );
    }

    /**
     * What a basket comes to, with SST and the branch's dine-in service
     * charge, worked out the way the order will be.
     *
     * @return array{subtotal: string, service_charge: string, sst: string, total: string, sst_included: bool, sst_rate: string, service_rate: string}
     */
    public function estimate(Branch $branch, string $subtotal): array
    {
        $settings = RestaurantSetting::current();
        $rate = $settings->sst_rate;
        $sst = Money::ZERO;
        $net = $subtotal;

        if ($settings->sst_enabled) {
            if ($settings->prices_include_sst) {
                $sst = Sst::includedIn($subtotal, $rate);
                $net = bcsub($subtotal, $sst, 2);
            } else {
                $sst = Sst::addedTo($subtotal, $rate);
            }
        }

        $serviceRate = $branch->chargesServiceCharge() ? $branch->service_charge_rate : Money::ZERO;
        $service = Money::percentOf($net, $serviceRate);
        $serviceSst = $settings->sst_enabled ? Sst::addedTo($service, $rate) : Money::ZERO;
        $total = Money::sum([$subtotal, $settings->prices_include_sst ? Money::ZERO : $sst, $service, $serviceSst]);

        return [
            'subtotal' => $subtotal,
            'service_charge' => $service,
            'sst' => bcadd($sst, $serviceSst, 2),
            'total' => $settings->round_to_five_sen ? Money::roundToFiveSen($total) : $total,
            'sst_included' => $settings->sst_enabled && $settings->prices_include_sst,
            'sst_rate' => rtrim(rtrim($rate, '0'), '.'),
            'service_rate' => rtrim(rtrim($serviceRate, '0'), '.'),
        ];
    }

    /**
     * The QR code for a link, as SVG.
     */
    public static function svg(string $url, int $size = 240): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)))->writeString($url);

        // Without the XML declaration, so it can sit inside a page.
        return preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }
}
