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
use Storipress\Webflow\Objects\Collection;
use Storipress\Webflow\Objects\SimpleCollection;
use Throwable;

final readonly class PullWebflowCollections
{
    /**
     * @param  array{
     *     refresh: bool,
     * }  $args
     * @return array<int, Collection>
     *
     * @throws UnexpectedValueException
     * @throws WebflowHttpException
     * @throws Throwable
     */
    public function __invoke($_, array $args): array
    {
        $webflow = Webflow::retrieve();

        if (! $args['refresh'] && $webflow->config->raw_collections) {
            return $webflow->config->raw_collections;
        }

        $siteId = $webflow->config->site_id;

        if (! is_not_empty_string($siteId)) {
            return [];
        }

        try {
            $rawCollections = $this->fetch($siteId);
        } catch (HttpUnauthorized) {
            $webflow->config->update(['expired' => true]);

            throw new HttpException(ErrorCode::WEBFLOW_UNAUTHORIZED);
        }

        $collections = array_map(function ($item) use ($rawCollections) {
            if (! is_array($item)) {
                return $item;
            }

            foreach ($rawCollections as $collection) {
                if ($collection->id === $item['id']) {
                    $encoded = json_encode($collection);

                    if ($encoded === false) {
                        return $collection;
                    }

                    $decoded = json_decode($encoded, true);

                    if (! is_array($decoded)) {
                        return $collection;
                    }

                    if (! empty($item['mappings'])) {
                        $decoded['mappings'] = $item['mappings'];
                    }

                    return $decoded;
                }
            }

            return $item;
        }, $webflow->config->collections);

        $webflow->config->update([
            'collections' => $collections,
            'raw_collections' => $rawCollections,
        ]);

        UserActivity::log(
            name: 'webflow.collections.pull',
            data: [
                'site_id' => $siteId,
            ],
        );

        return $rawCollections;
    }

    /**
     * @return array<int, Collection>
     *
     * @throws WebflowHttpException
     * @throws UnexpectedValueException
     */
    protected function fetch(string $siteId): array
    {
        $collection = app('webflow')->collection();

        $collections = $collection->list($siteId);

        return array_map(function (SimpleCollection $item) use ($collection) {
            return $collection->get($item->id);
        }, $collections);
    }
}
