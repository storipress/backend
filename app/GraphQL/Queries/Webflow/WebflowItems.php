<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Webflow;

use Generator;
use Storipress\Webflow\Exceptions\Exception;
use Storipress\Webflow\Exceptions\HttpHitRateLimit;
use Storipress\Webflow\Exceptions\HttpNotFound;
use Storipress\Webflow\Objects\Item as ItemObject;

use function Sentry\captureException;

final readonly class WebflowItems
{
    /**
     * @param  array{
     *     collection_id: non-empty-string,
     * }  $args
     * @return array<int, array{
     *     id: non-empty-string,
     *     name: non-empty-string,
     *     slug: non-empty-string,
     * }>
     */
    public function __invoke(null $_, array $args): array
    {
        $items = iterator_to_array($this->all($args['collection_id']));

        return array_map(function (ItemObject $item) {
            return [
                'id' => $item->id,
                'name' => $item->fieldData->name,
                'slug' => $item->fieldData->slug,
            ];
        }, $items);
    }

    /**
     * @return Generator<ItemObject>
     */
    public function all(string $id): Generator
    {
        $api = app('webflow')->item();

        $offset = 0;

        $limit = 100;

        do {
            try {
                [
                    'data' => $items,
                    'pagination' => $pagination,
                ] = $api->list($id, $offset, $limit);

                foreach ($items as $item) {
                    yield $item;
                }

                $offset += $limit;
            } catch (HttpHitRateLimit|HttpNotFound) {
                break;
            } catch (Exception $e) {
                captureException($e);

                break;
            }
        } while ($offset < $pagination->total);
    }
}
