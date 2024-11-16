<?php

namespace App\AutoPosting\Facebook;

use App\AutoPosting\Dispatcher;
use App\AutoPosting\Layers\PlatformCheckerLayer as BaseLayer;
use App\Exceptions\ErrorException;
use App\Models\Tenants\Integration;
use Throwable;

class PlatformCheckerLayer extends BaseLayer
{
    /**
     * {@inheritdoc}
     */
    public function handle(Dispatcher $dispatcher, array $data, array $extra): bool
    {
        // e.g.
        // 1. check integration is enabled against the database
        // 2. check token is valid by sending a request

        $integration = Integration::find('facebook');

        if ($integration === null) {
            return false;
        }

        if ($integration->activated_at === null) {
            return false;
        }

        // other checks

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function logStopped(ErrorException $e, string $layer): void
    {
        // do nothing
    }

    /**
     * {@inheritdoc}
     */
    public function logFailed(Throwable $e, string $layer): void
    {
        // do nothing
    }

    /**
     * {@inheritdoc}
     */
    public function reportStopped(ErrorException $e): void
    {
        // do nothing
    }

    /**
     * {@inheritdoc}
     */
    public function reportFailed(Throwable $e): void
    {
        // do nothing
    }
}
