<?php

namespace App\Http\Controllers\Partners\Zapier;

use App\Exceptions\ErrorCode;
use App\Models\Tenant;
use App\Models\Tenants\Webhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateWebhookController extends ZapierController
{
    public function __invoke(Request $request): JsonResponse
    {
        $tenant = auth()->user();

        if (! $tenant instanceof Tenant) {
            // unauthorized
            return $this->failed(ErrorCode::ZAPIER_MISSING_CLIENT, 401);
        }

        $validator = Validator::make($request->all(), [
            'topic' => 'required|string',
            'hook_url' => 'required|string|url',
        ]);

        if ($validator->fails()) {
            return $this->failed(ErrorCode::ZAPIER_INVALID_PAYLOAD, 400);
        }

        $topic = $request->input('topic');

        // validate webhooks
        if (! $this->validate($topic)) {
            return $this->failed(ErrorCode::ZAPIER_INVALID_TOPIC, 400);
        }

        $url = $request->input('hook_url');

        $id = Str::uuid();

        $tenant->run(function () use ($id, $topic, $url) {
            // subscribe webhook
            Webhook::create([
                'id' => $id,
                'platform' => 'zapier',
                'topic' => $topic,
                'url' => $url,
            ]);
        });

        return response()->json(['id' => $id]);
    }
}
