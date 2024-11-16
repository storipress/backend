<?php

namespace App\Http\Controllers;

use App\Exceptions\ErrorCode;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use DispatchesJobs;

    /**
     * @param  array<string, int|string>  $replaces
     */
    protected function failed(int $code, int $statusCode = 401, array $replaces = []): JsonResponse
    {
        return response()->json(
            [
                'code' => $code,
                'message' => strtr(ErrorCode::$statusTexts[$code], $replaces),
            ],
            $statusCode,
        );
    }
}
