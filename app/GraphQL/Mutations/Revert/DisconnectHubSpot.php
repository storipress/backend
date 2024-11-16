<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Revert;

use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use Throwable;

use function Sentry\captureException;

final readonly class DisconnectHubSpot
{
    /**
     * @param  array{}  $args
     */
    public function __invoke(null $_, array $args): bool
    {
        $tenant = tenant();

        if (!($tenant instanceof Tenant)) {
            throw new HttpException(ErrorCode::OAUTH_BAD_REQUEST);
        }

        $token = config('services.revert.token');

        if (!is_not_empty_string($token)) {
            throw new HttpException(ErrorCode::OAUTH_BAD_REQUEST);
        }

        try {
            app('revert')
                ->setToken($token)
                ->setCustomerId(sprintf('%s-hubspot', $tenant->id))
                ->connection()
                ->delete();
        } catch (Throwable $e) {
            captureException($e);

            return false;
        }

        Integration::where('key', '=', 'hubspot')->update([
            'internals' => null,
            'activated_at' => null,
        ]);

        return true;
    }
}
