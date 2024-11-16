<?php

namespace App\Http\Controllers\Partners\Zapier;

use App\Exceptions\ErrorCode;
use App\Models\Tenant;
use App\Models\Tenants\Webhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RemoveWebhookController extends ZapierController
{
    public function __invoke(Request $request): JsonResponse
    {
        $tenant = auth()->user();

        if (! $tenant instanceof Tenant) {
            return $this->failed(ErrorCode::ZAPIER_MISSING_CLIENT, 401);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->failed(ErrorCode::ZAPIER_INVALID_PAYLOAD, 400);
        }

        $id = $request->input('id');

        $tenant->run(function () use ($id) {
            // unsubscribe webhook
            Webhook::where('id', $id)->delete();
        });

        return response()->json(['success' => true]);
    }
}
