<?php

namespace App\Http\Controllers\Assistants;

use App\Enums\Assistant\Type;
use App\Http\Controllers\Controller;
use App\Models\Assistant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SavePromptController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! ($user instanceof User)) {
            return response()->json(['ok' => false]);
        }

        Assistant::create([
            'uuid' => $request->input('uuid'),
            'chat_id' => Str::of('unknown-')->append(Str::random())->lower()->toString(),
            'tenant_id' => tenant('id'),
            'user_id' => $user->id,
            'model' => $request->input('model'),
            'type' => Type::general(),
            'data' => $request->input('data'),
        ]);

        return response()->json(['ok' => true]);
    }
}
