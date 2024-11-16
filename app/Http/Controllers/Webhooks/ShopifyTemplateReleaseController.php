<?php

namespace App\Http\Controllers\Webhooks;

use App\Builder\ReleaseEventsBuilder;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopifyTemplateReleaseController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        dispatch(function () {
            $tenants = Tenant::initialized()
                ->where('data->custom_site_template', '=', true)
                ->where('data->custom_site_template_path', '=', 'assets/storipress/templates/shopify.zip')
                ->lazyById(50);

            runForTenants(
                function () {
                    (new ReleaseEventsBuilder())->handle('shopify:released');

                    sleep(60);
                },
                $tenants,
            );
        });

        return response()->json(['ok' => true]);
    }
}
