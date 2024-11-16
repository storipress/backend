<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Webflow;

use App\Enums\Webflow\CollectionType;
use App\Events\Partners\Webflow\CollectionConnected;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\Tenants\Integrations\Webflow;
use Storipress\Webflow\Exceptions\Exception;
use Storipress\Webflow\Exceptions\HttpNotFound;
use Storipress\Webflow\Exceptions\HttpUnauthorized;

use function Sentry\captureException;

final readonly class UpdateWebflowCollection
{
    /**
     * @param  array{
     *     type: CollectionType,
     *     value: string,
     * }  $args
     */
    public function __invoke(null $_, array $args): bool
    {
        $tenant = tenant_or_fail();

        $webflow = Webflow::retrieve();

        if ($webflow->config->site_id === null) {
            throw new HttpException(ErrorCode::WEBFLOW_MISSING_SITE_ID);
        }

        foreach ($webflow->config->collections as $type => $current) {
            if ($args['type']->value !== $type && $current['id'] === $args['value']) {
                throw new HttpException(ErrorCode::WEBFLOW_COLLECTION_ID_CONFLICT);
            }
        }

        try {
            $collection = app('webflow')->collection()->get($args['value']);
        } catch (HttpNotFound) {
            throw new HttpException(ErrorCode::WEBFLOW_INVALID_COLLECTION_ID);
        } catch (HttpUnauthorized) {
            $webflow->config->update(['expired' => true]);

            throw new HttpException(ErrorCode::WEBFLOW_UNAUTHORIZED);
        } catch (Exception $e) {
            captureException($e);

            throw new HttpException(ErrorCode::WEBFLOW_INTERNAL_ERROR);
        }

        $webflow->config->update([
            'collections' => [
                $args['type']->value => $collection,
            ],
            'onboarding' => [
                'collection' => [
                    $args['type']->value => true,
                ],
            ],
        ]);

        CollectionConnected::dispatch(
            $tenant->id,
            $args['type']->value, // @phpstan-ignore-line
        );

        return true;
    }
}
