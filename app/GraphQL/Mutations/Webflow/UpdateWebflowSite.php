<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Webflow;

use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\Webflow;
use Storipress\Webflow\Exceptions\Exception;
use Storipress\Webflow\Exceptions\HttpNotFound;
use Storipress\Webflow\Exceptions\HttpUnauthorized;

use function Sentry\captureException;

final readonly class UpdateWebflowSite
{
    /**
     * @param  array{
     *     value: string,
     * }  $args
     */
    public function __invoke(null $_, array $args): bool
    {
        $tenant = tenant();

        if (!($tenant instanceof Tenant)) {
            throw new NotFoundHttpException();
        }

        try {
            $site = app('webflow')->site()->get($args['value']);
        } catch (HttpNotFound) {
            throw new HttpException(ErrorCode::WEBFLOW_INVALID_SITE_ID);
        } catch (HttpUnauthorized) {
            Webflow::retrieve()->config->update(['expired' => true]);

            throw new HttpException(ErrorCode::WEBFLOW_UNAUTHORIZED);
        } catch (Exception $e) {
            captureException($e);

            throw new HttpException(ErrorCode::WEBFLOW_INTERNAL_ERROR);
        }

        $webflow = Webflow::retrieve();

        $webflow->config->update([
            'onboarding' => [
                'site' => is_not_empty_string($webflow->config->domain),
            ],
            'site_id' => $site->id,
        ]);

        $data = $tenant->webflow_data;

        $data['site_id'] = $site->id;

        $tenant->webflow_data = $data;

        $tenant->save();

        return true;
    }
}
