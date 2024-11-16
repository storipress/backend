<?php

namespace App\AutoPosting\LinkedIn;

use App\AutoPosting\Dispatcher;
use App\AutoPosting\Layers\PlatformCheckerLayer as BaseLayer;
use App\Exceptions\ErrorCode;
use App\Exceptions\ErrorException;
use App\Models\Tenants\Integration;
use App\SDK\LinkedIn\LinkedIn;

class PlatformCheckerLayer extends BaseLayer
{
    use HasFailedHandler;
    use HasStoppedHandler;

    public function __construct(protected LinkedIn $app) {}

    /**
     * {@inheritdoc}
     *
     * @param  array{}  $data
     *
     * @throws ErrorException
     */
    public function handle(Dispatcher $dispatcher, array $data, array $extra): bool
    {
        $integration = Integration::find('linkedin');

        if ($integration === null) {
            return false;
        }

        if ($integration->activated_at === null) {
            return false;
        }

        if (empty($integration->data)) {
            throw new ErrorException(ErrorCode::LINKEDIN_INTEGRATION_NOT_CONNECT);
        }

        if (empty($integration->internals)) {
            throw new ErrorException(ErrorCode::LINKEDIN_INTEGRATION_NOT_CONNECT);
        }

        if (empty($integration->internals['access_token']) || ! is_string($integration->internals['access_token'])) {
            throw new ErrorException(ErrorCode::LINKEDIN_INTEGRATION_NOT_CONNECT);
        }

        $accessToken = $integration->internals['access_token'];

        $refreshToken = $integration->internals['refresh_token'];

        // token expired
        if (! $this->app->introspect($accessToken)) {
            // refresh token expired
            if (! $this->app->introspect($refreshToken)) {
                // revoke integration
                $integration->revoke();

                throw new ErrorException(ErrorCode::LINKEDIN_INTEGRATION_NOT_CONNECT);
            }

            $accessToken = $this->app->refresh($refreshToken);

            if ($accessToken === null) {
                throw new ErrorException(ErrorCode::LINKEDIN_INTEGRATION_NOT_CONNECT);
            }

            $internals = $integration->internals;

            $internals['access_token'] = $accessToken;

            $integration->internals = $internals;

            $integration->save();
        }

        return true;
    }
}
