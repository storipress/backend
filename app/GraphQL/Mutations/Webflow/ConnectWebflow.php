<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Webflow;

use App\Events\Partners\Webflow\OAuthConnecting;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\Tenant;
use App\Models\Tenants\User as TenantUser;
use App\Models\User as CentralUser;
use Illuminate\Support\Arr;
use Laravel\Socialite\Facades\Socialite;
use Storipress\SocialiteProviders\Webflow\WebflowProvider;

final readonly class ConnectWebflow
{
    /**
     * @param  array{}  $args
     */
    public function __invoke(null $_, array $args): string
    {
        $tenant = tenant();

        if (!($tenant instanceof Tenant)) {
            throw new HttpException(ErrorCode::OAUTH_BAD_REQUEST);
        }

        $socialite = Socialite::driver('webflow');

        if (!($socialite instanceof WebflowProvider)) {
            throw new HttpException(ErrorCode::OAUTH_INTERNAL_ERROR);
        }

        $user = auth()->user();

        if (!($user instanceof CentralUser)) {
            throw new HttpException(ErrorCode::OAUTH_UNAUTHORIZED_REQUEST);
        }

        $manipulator = TenantUser::find($user->getAuthIdentifier());

        if (!($manipulator instanceof TenantUser)) {
            throw new HttpException(ErrorCode::OAUTH_FORBIDDEN_REQUEST);
        }

        if (!in_array($manipulator->role, ['owner', 'admin'], true)) {
            throw new HttpException(ErrorCode::OAUTH_FORBIDDEN_REQUEST);
        }

        $data = $user->access_token->data ?: [];

        Arr::set($data, 'integration.webflow.key', $tenant->id);

        $user->access_token->update(['data' => $data]);

        OAuthConnecting::dispatch($tenant->id);

        return $socialite
            ->redirectUrl(secure_url(route('oauth.webflow', [], false)))
            ->setScopes($this->scopes())
            ->stateless()
            ->with(['state' => $user->access_token->token])
            ->redirect()
            ->getTargetUrl();
    }

    /**
     * @return array<int, string>
     */
    public function scopes(): array
    {
        return [
            'assets:read',
            'assets:write',
            'authorized_user:read',
            'cms:read',
            'cms:write',
            'custom_code:read',
            'custom_code:write',
            'ecommerce:read',
            'forms:read',
            'forms:write',
            'pages:read',
            'pages:write',
            'sites:read',
            'sites:write',
            'users:read',
        ];
    }
}
