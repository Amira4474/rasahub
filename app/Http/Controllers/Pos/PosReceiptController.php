<?php

namespace App\Http\Controllers\Pos;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RestaurantSetting;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * A receipt laid out for a thermal printer, printed through the browser.
 */
class PosReceiptController extends Controller
{
    public function __invoke(Request $request, string $order): View
    {
        $user = $request->user();

        abort_if($user === null, 403);

        abort_unless($user->can(Permission::TakeOrders) || $user->can(Permission::ViewOrders), 403);

        $order = Order::where('ulid', $order)
            ->with(['lines.parts', 'lines.modifiers', 'payments', 'branch', 'diningTable', 'customer'])
            ->firstOrFail();

        abort_unless($user->canAccessBranch($order->branch), 404);

        $settings = RestaurantSetting::current();

        return view('pos.receipt', [
            'order' => $order,
            'settings' => $settings,
            'business' => app(TenantContext::class)->business(),
            'logo' => $settings->receipt_logo_path ? Storage::disk('public')->url($settings->receipt_logo_path) : null,
            'timezone' => $order->branch->effectiveTimezone(),
        ]);
    }
}
