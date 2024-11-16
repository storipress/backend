<?php

namespace App\GraphQL\Mutations\Page;

use App\Events\Entity\Page\PageUpdated;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenant;
use App\Models\Tenants\Page;
use App\Models\Tenants\UserActivity;
use Exception;
use Webmozart\Assert\Assert;

class MovePageAfter
{
    /**
     * @param  array{
     *     id: string,
     *     target_id: string,
     * }  $args
     *
     * @throws Exception
     */
    public function __invoke($_, array $args): Page
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $page = Page::find($args['id']);

        $target = Page::find($args['target_id']);

        if (!($page instanceof Page) || !($target instanceof Page)) {
            throw new NotFoundHttpException();
        }

        $original = $page->order;

        $page->moveAfter($target);

        PageUpdated::dispatch($tenant->id, $page->id, ['order']);

        UserActivity::log(
            name: 'page.order.change',
            subject: $page,
            data: [
                'old' => $original,
                'new' => $page->order,
            ],
        );

        return $page;
    }
}
