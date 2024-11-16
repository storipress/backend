<?php

namespace App\Http\Controllers\Partners\LinkedIn;

use App\Exceptions\ErrorCode;
use App\Http\Controllers\Partners\PartnerController;
use App\Models\Tenants\User as TenantUser;
use App\Models\User;
use App\SDK\LinkedIn\LinkedIn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ConnectController extends PartnerController
{
    /**
     * redirect to authorize url
     */
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $user = auth()->user();

        if ($user === null) {
            return $this->failed(ErrorCode::OAUTH_UNAUTHORIZED_REQUEST);
        }

        if (! ($user instanceof User)) {
            return $this->failed(ErrorCode::OAUTH_BAD_REQUEST);
        }

        $manipulator = TenantUser::find($user->getAuthIdentifier());

        if (! in_array($manipulator?->role, ['owner', 'admin'], true)) {
            return $this->failed(ErrorCode::OAUTH_FORBIDDEN_REQUEST);
        }

        $key = tenant('id');

        if (! is_not_empty_string($key)) {
            return $this->failed(ErrorCode::OAUTH_INTERNAL_ERROR);
        }

        $data = $user->access_token->data ?: [];

        Arr::set($data, 'integration.linkedin.key', $key);

        $user->access_token->update(['data' => $data]);

        return (new LinkedIn())->redirect($user->access_token->token);
    }
}
