<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Webflow;

use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\Tenants\Integrations\Webflow;
use Storipress\Webflow\Exceptions\Exception;
use Storipress\Webflow\Exceptions\HttpUnauthorized;

use function Sentry\captureException;

final readonly class UpdateWebflowDomain
{
    /**
     * @param  array{
     *     value: string,
     * }  $args
     */
    public function __invoke(null $_, array $args): bool
    {
        $webflow = Webflow::retrieve();

        if ($webflow->config->site_id === null) {
            throw new HttpException(ErrorCode::WEBFLOW_MISSING_SITE_ID);
        }

        try {
            $site = app('webflow')->site()->get($webflow->config->site_id);
        } catch (HttpUnauthorized) {
            $webflow->config->update(['expired' => true]);

            throw new HttpException(ErrorCode::WEBFLOW_UNAUTHORIZED);
        } catch (Exception $e) {
            captureException($e);

            throw new HttpException(ErrorCode::WEBFLOW_INTERNAL_ERROR);
        }

        $available = array_column($site->customDomains, 'url');

        $available[] = $site->defaultDomain;

        if (!in_array($args['value'], $available, true)) {
            throw new HttpException(ErrorCode::WEBFLOW_INVALID_DOMAIN);
        }

        Webflow::retrieve()->config->update([
            'onboarding' => [
                'site' => true,
            ],
            'domain' => $args['value'],
        ]);

        return true;
    }
}
