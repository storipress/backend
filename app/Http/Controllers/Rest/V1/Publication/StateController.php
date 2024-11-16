<?php

namespace App\Http\Controllers\Rest\V1\Publication;

use App\Enums\Tenant\State;
use App\Http\Controllers\Rest\RestController;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webmozart\Assert\Assert;

class StateController extends RestController
{
    public function __invoke(Request $request): JsonResponse
    {
        /**
         * @return array<array-key, State>
         */
        $closure = function () use ($request): array {
            $client = $request->route('client');

            Assert::stringNotEmpty($client);

            $tenant = Tenant::withTrashed()->find($client);

            return [
                'state' => $tenant === null ? State::notFound() : $tenant->state,
            ];
        };

        if ($request->input('no-cache')) {
            $this->forget();
        }

        return response()->json($this->remember($closure));
    }
}
