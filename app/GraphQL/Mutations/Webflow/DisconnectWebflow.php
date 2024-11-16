<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Webflow;

use App\Events\Partners\Webflow\OAuthDisconnected;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\Webflow;
use App\Models\Tenants\User as TenantUser;
use App\Models\Tenants\UserActivity;
use App\Models\User as CentralUser;
use Illuminate\Http\Client\RequestException;
use Throwable;

use function Sentry\captureException;

final readonly class DisconnectWebflow
{
    /**
     * @param  array{}  $args
     */
    public function __invoke(null $_, array $args): bool
    {
        $tenant = tenant();

        if (! ($tenant instanceof Tenant)) {
            throw new HttpException(ErrorCode::OAUTH_BAD_REQUEST);
        }

        $user = auth()->user();

        if (! ($user instanceof CentralUser)) {
            throw new HttpException(ErrorCode::OAUTH_UNAUTHORIZED_REQUEST);
        }

        $manipulator = TenantUser::find($user->getAuthIdentifier());

        if (! ($manipulator instanceof TenantUser)) {
            throw new HttpException(ErrorCode::OAUTH_FORBIDDEN_REQUEST);
        }

        if (! in_array($manipulator->role, ['owner', 'admin'], true)) {
            throw new HttpException(ErrorCode::OAUTH_FORBIDDEN_REQUEST);
        }

        $webflow = Webflow::retrieve();

        if (! is_not_empty_string($webflow->config->access_token)) {
            return false;
        }

        $revoked = $webflow->config->expired;

        try {
            if (! $revoked) {
                $revoked = app('http2')
                    ->post('https://api.webflow.com/oauth/revoke_authorization', [
                        'client_id' => config('services.webflow.client_id'),
                        'client_secret' => config('services.webflow.client_secret'),
                        'access_token' => $webflow->config->access_token,
                    ])
                    ->throw()
                    ->json('did_revoke', false);
            }
        } catch (RequestException $e) {
            $revoked = $e->getCode() === 404;

            if (! $revoked) {
                captureException($e);
            }
        } catch (Throwable $e) {
            $revoked = false;

            captureException($e);
        }

        if (! $revoked) {
            return false;
        }

        UserActivity::log(
            name: 'integration.disconnect',
            data: [
                'key' => 'webflow',
            ],
        );

        OAuthDisconnected::dispatch($tenant->id);

        return true;
    }
}
