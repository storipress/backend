<?php

namespace App\Http\Controllers\Assistants;

use App\Http\Controllers\Controller;
use App\Models\Assistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatchPromptController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $uuid = $request->input('uuid');

        if (! is_not_empty_string($uuid)) {
            return response()->json(['ok' => false]);
        }

        $chatId = $request->input('chat_id');

        if (! is_not_empty_string($chatId)) {
            return response()->json(['ok' => false]);
        }

        $assistant = Assistant::where('uuid', '=', $uuid)->first();

        if (! ($assistant instanceof Assistant)) {
            return response()->json(['ok' => false]);
        }

        $assistant->update(['chat_id' => $chatId]);

        return response()->json(['ok' => true]);
    }
}
