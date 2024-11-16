<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

final class SiteController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(?string $site = null): JsonResponse
    {
        $columns = ['id', 'name', 'workspace', 'custom_domain'];

        if ($site === null) {
            $data = Tenant::get($columns);
        } else {
            $key = sprintf('site-%s', md5($site));

            $data = Cache::remember(
                $key,
                now()->addMinutes(),
                fn () => Tenant::with(['cloudflare_page'])
                    ->addSelect($columns)
                    ->addSelect(['cloudflare_page_id'])
                    ->find($site)
                    ?->only(
                        array_merge(
                            $columns,
                            ['cf_pages_url'],
                        ),
                    ),
            );
        }

        return response()->json($data);
    }
}
