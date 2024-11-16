<?php

namespace App\Http\Controllers\Partners\Zapier;

use App\Exceptions\ErrorCode;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SearchArticleController extends ZapierController
{
    /**
     * redirect to authorize url
     */
    public function __invoke(Request $request): JsonResponse
    {
        $tenant = auth()->user();

        if (!$tenant instanceof Tenant) {
            // unauthorized
            return $this->failed(ErrorCode::ZAPIER_MISSING_CLIENT, 401);
        }

        $validator = Validator::make($request->all(), [
            'topic' => 'required|string',
            'key' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->failed(ErrorCode::ZAPIER_INVALID_PAYLOAD, 400);
        }

        /** @var string $key */
        $key = $request->input('key');

        $data = $tenant->run(function () use ($key) {
            /** @var Article|null $article */
            $article = Article::search(trim($key))->first();

            return $article ? [$article->toWebhookArray()] : [];
        });

        return response()->json($data);
    }
}
