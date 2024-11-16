<?php

namespace App\Http\Controllers\Partners;

use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

abstract class PartnerController extends Controller
{
    /**
     * @param  array<string, string>  $replaces
     */
    protected function failed(
        int $code,
        int $statusCode = 401,
        array $replaces = [],
    ): JsonResponse {
        return response()->json(
            [
                'code' => $code,
                'message' => ErrorCode::getMessage($code, $replaces),
            ],
            $statusCode,
        );
    }

    /**
     * @param  array<string, string>  $replaces
     */
    protected function oauthFailed(
        int $code,
        array $replaces = [],
    ): RedirectResponse {
        $url = $this->oauthResultUrl(
            [
                'code' => (string) $code,
                'message' => ErrorCode::getMessage($code, $replaces),
            ],
            false,
        );

        return redirect()->away($url);
    }

    /**
     * @param  array<string, string>  $queries
     */
    protected function oauthResultUrl(
        array $queries = [],
        bool $v2 = true,
    ): string {
        return sprintf(
            '%s?%s',
            app_url($v2 ? 'redirect' : 'social-connected.html'),
            http_build_query($queries),
        );
    }
}
