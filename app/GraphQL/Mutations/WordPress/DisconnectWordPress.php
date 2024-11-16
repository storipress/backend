<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\WordPress;

use App\Events\Partners\WordPress\Disconnected;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\WordPress;
use App\Models\Tenants\User as TenantUser;
use App\Models\Tenants\UserActivity;
use App\Models\User as CentralUser;
use Storipress\WordPress\Exceptions\IncorrectPasswordException;
use Storipress\WordPress\Exceptions\RestForbiddenException;
use Throwable;

use function Sentry\captureException;

final readonly class DisconnectWordPress
{
    /**
     * @param  array{
     * }  $args
     */
    public function __invoke(null $_, array $args): bool
    {
        $tenant = tenant();

        if (!($tenant instanceof Tenant)) {
            throw new HttpException(ErrorCode::OAUTH_BAD_REQUEST);
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

        if (!WordPress::retrieve()->is_connected) {
            return true;
        }

        try {
            app('wordpress')
                ->request()
                ->post('/storipress/disconnect', []);
        } catch (RestForbiddenException|IncorrectPasswordException) {
            // ignored
        } catch (Throwable $e) {
            captureException($e);
        }

        UserActivity::log(
            name: 'integration.disconnect',
            data: [
                'key' => 'wordpress',
            ],
        );

        Disconnected::dispatch($tenant->id);

        return true;
    }
}
