<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\DiningTable;
use App\Models\FloorArea;
use App\Models\RestaurantSetting;
use App\Sales\QrOrdering;
use App\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page a customer reaches by scanning a table's QR code. Public: the
 * random code in the link is the only key, and it names one table.
 */
class QrOrderController extends Controller
{
    /**
     * An A4 sheet of a floor area's tables, one card per table with its QR
     * code, to print and put on the tables.
     */
    public function codes(Request $request, string $area): Response
    {
        $user = $request->user();

        abort_if($user === null || ! $user->can(Permission::ViewTables), 403);

        $business = app(TenantContext::class)->business() ?? abort(404);
        abort_unless($business->subscription?->plan->includes('qr_ordering'), 403);

        $area = FloorArea::where('ulid', $area)->with(['branch', 'tables' => fn ($q) => $q->where('is_active', true)])->firstOrFail();
        abort_unless($user->can('view', $area), 404);

        $settings = RestaurantSetting::current();

        $cards = $area->tables->map(fn (DiningTable $table): array => [
            'name' => $table->name,
            'qr' => 'data:image/svg+xml;base64,'.base64_encode(QrOrdering::svg($table->qrUrl($business->acronym), 300)),
        ]);

        return Pdf::loadView('qr.codes', [
            'business' => $settings->qr_heading ?: $business->name,
            'area' => $area,
            'cards' => $cards,
            'primary' => $settings->qr_primary_color,
        ])->setPaper('a4')->download(Str::slug($business->acronym.' '.$area->branch->name.' '.$area->name.' qr codes').'.pdf');
    }

    public function __invoke(string $business, string $token): View
    {
        $found = QrOrdering::business($business) ?? abort(404);

        return $found->run(function () use ($found, $token): View {
            $table = QrOrdering::table($token) ?? abort(404);
            $settings = RestaurantSetting::current();

            return view('qr.page', [
                'business' => $found,
                'token' => $token,
                'title' => $settings->qr_heading ?: $found->name,
                'primary' => $settings->qr_primary_color,
                'background' => $settings->qr_background_color,
                'tableName' => $table->name,
                'logo' => $settings->qr_logo_path ? Storage::disk('public')->url($settings->qr_logo_path) : null,
            ]);
        });
    }
}
