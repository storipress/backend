<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stancl\Tenancy\Database\TenantCollection;

final class PusherAppsController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var TenantCollection<int, Tenant> $tenants */
        $tenants = Tenant::get(['id', 'name', 'wss_secret']);

        return response()->json($tenants->toArray());
    }
}
