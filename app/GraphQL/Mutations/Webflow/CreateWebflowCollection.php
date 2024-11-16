<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Webflow;

use App\Enums\Webflow\CollectionType;
use App\Events\Partners\Webflow\CollectionCreating;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\Webflow;
use Illuminate\Support\Str;

final readonly class CreateWebflowCollection
{
    /**
     * @param  array{
     *     type: CollectionType,
     * }  $args
     */
    public function __invoke($_, array $args): bool
    {
        $tenant = tenant();

        if (! ($tenant instanceof Tenant)) {
            throw new HttpException(ErrorCode::NOT_FOUND);
        }

        $webflow = Webflow::retrieve();

        if ($webflow->config->site_id === null) {
            throw new HttpException(ErrorCode::WEBFLOW_MISSING_SITE_ID);
        }

        $slug = $args['type']->value;

        $name = Str::of($slug)->plural()->title()->value();

        $rawCollections = $webflow->config->raw_collections;

        foreach ($rawCollections as $rawCollection) {
            if (Str::lower($rawCollection->displayName) !== Str::lower($name) && $rawCollection->slug !== $slug) {
                continue;
            }

            throw new HttpException(
                ErrorCode::WEBFLOW_DUPLICATE_COLLECTION,
                [
                    'name' => $rawCollection->displayName,
                    'slug' => $rawCollection->slug,
                ],
            );
        }

        CollectionCreating::dispatch(
            $tenant->id,
            $args['type'],
        );

        return true;
    }
}
