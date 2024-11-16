<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Webflow;

use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\Tenants\Integrations\Webflow;
use App\Models\Tenants\UserActivity;
use Storipress\Webflow\Exceptions\HttpException as WebflowHttpException;
use Storipress\Webflow\Exceptions\HttpUnauthorized;
use Storipress\Webflow\Exceptions\UnexpectedValueException;
use Storipress\Webflow\Objects\Site;
use Throwable;

final readonly class PullWebflowSites
{
    /**
     * @param  array{
     *     refresh: bool,
     * }  $args
     * @return array<int, Site>
     *
     * @throws UnexpectedValueException
     * @throws WebflowHttpException
     * @throws Throwable
     */
    public function __invoke($_, array $args): array
    {
        $webflow = Webflow::retrieve();

        if (! $args['refresh'] && $webflow->config->raw_sites) {
            return $webflow->config->raw_sites;
        }

        try {
            $sites = app('webflow')->site()->list();
        } catch (HttpUnauthorized) {
            $webflow->config->update(['expired' => true]);

            throw new HttpException(ErrorCode::WEBFLOW_UNAUTHORIZED);
        }

        $webflow->config->update([
            'raw_sites' => $sites,
        ]);

        UserActivity::log(
            name: 'webflow.sites.pull',
        );

        return $sites;
    }
}
