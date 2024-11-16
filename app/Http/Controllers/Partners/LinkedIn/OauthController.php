<?php

namespace App\Http\Controllers\Partners\LinkedIn;

use App\Events\Partners\LinkedIn\OAuthConnected;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Partners\PartnerController;
use App\Models\Tenant;
use App\Models\Tenants\UserActivity;
use App\Models\User;
use App\Resources\Partners\LinkedIn\User as LinkedInUser;
use App\SDK\LinkedIn\LinkedIn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

use function Sentry\captureException;

class OauthController extends PartnerController
{
    /**
     * Handle OAuth Callback.
     */
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $user = auth()->user();

        if ($user === null) {
            return $this->failed(ErrorCode::OAUTH_UNAUTHORIZED_REQUEST);
        }

        if (!($user instanceof User)) {
            return $this->failed(ErrorCode::OAUTH_BAD_REQUEST);
        }

        $data = $user->access_token->data ?: [];

        $key = Arr::get($data, 'integration.linkedin.key');

        if (!is_string($key) || empty($key)) {
            return $this->failed(ErrorCode::OAUTH_INTERNAL_ERROR);
        }

        if ($request->get('error')) {
            Log::channel('slack')->info('LinkedIn OAuth Error', [
                'env' => app()->environment(),
                'error' => $request->get('error'),
                'error_description' => $request->get('error_description'),
                'publication' => $key,
            ]);

            return $this->failed(ErrorCode::OAUTH_INTERNAL_ERROR);
        }

        // avoid code used twice. @phpstan-ignore-next-line
        $code = sprintf('linkedin_code_%s', $request->get('code'));

        if (!Cache::add($code, true, 60)) {
            return $this->failed(ErrorCode::OAUTH_FORBIDDEN_REQUEST);
        }

        try {
            $payload = (new LinkedIn())->user();
        } catch (Throwable $e) {
            if (!Str::contains($e->getMessage(), '401 Unauthorized')) {
                captureException($e);
            }

            return $this->failed(ErrorCode::OAUTH_FORBIDDEN_REQUEST);
        }

        $linkedInUser = new LinkedInUser(
            id: $payload->id, // @phpstan-ignore-line
            name: $payload->name,
            email: $payload->email,
            avatar: $payload->avatar,
        );

        $tenant = Tenant::where('id', $key)->first();

        if (empty($tenant)) {
            return $this->failed(ErrorCode::OAUTH_INTERNAL_ERROR);
        }

        $tenant->run(fn () => UserActivity::log(
            name: 'integration.connect',
            data: [
                'key' => 'linkedin',
            ],
        ));

        OAuthConnected::dispatch(
            $payload->token,
            $payload->refreshToken,
            $linkedInUser,
            explode(',', $payload->approvedScopes[0]),
            $key,
        );

        return redirect()->away($this->oauthResultUrl(['response' => '[]'], false));
    }
}
