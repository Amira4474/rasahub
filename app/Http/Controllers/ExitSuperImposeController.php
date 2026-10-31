<?php

namespace App\Http\Controllers;

use App\Filament\Platform\Resources\Tenants\TenantResource;
use App\Models\Business;
use App\Tenancy\SuperImpose;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;

class ExitSuperImposeController extends Controller
{
    /**
     * Leave the tenant and return to its page in the platform panel.
     */
    public function __invoke(SuperImpose $superImpose): RedirectResponse
    {
        $user = auth()->user();
        $businessId = $user === null ? null : $superImpose->businessIdFor($user);

        $superImpose->exit();

        session()->regenerate();

        $business = $businessId === null ? null : Business::withTrashed()->find($businessId);

        return redirect()->to($business !== null
            ? TenantResource::getUrl('view', ['record' => $business], panel: 'platform')
            : Filament::getPanel('platform')->getUrl());
    }
}
