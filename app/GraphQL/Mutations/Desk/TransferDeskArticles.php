<?php

namespace App\GraphQL\Mutations\Desk;

use App\Builder\ReleaseEventsBuilder;
use App\Events\Entity\Article\ArticleDeskChanged;
use App\Events\Entity\Desk\DeskDeleted;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Desk;
use App\Models\Tenants\UserActivity;
use Segment\Segment;
use Webmozart\Assert\Assert;

class TransferDeskArticles
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): bool
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        /** @var Desk $from */
        $from = Desk::find($args['from_id']);

        /** @var int[] $ids */
        $ids = $from->articles()->pluck('id')->toArray();

        $from->articles()->update(['desk_id' => $args['to_id']]);

        Article::whereDeskId($args['to_id'])->chunk(500, fn ($articles) => $articles->searchable());

        foreach ($ids as $id) {
            ArticleDeskChanged::dispatch($tenant->id, $id, $from->id);
        }

        $trash = (bool) ($args['trash'] ?? false);

        if ($trash) {
            $from->delete();

            DeskDeleted::dispatch($tenant->id, $from->id);
        }

        UserActivity::log(
            name: 'desk.transfer',
            subject: $from,
            data: [
                'to' => $args['to_id'],
                'trash' => $trash,
            ],
        );

        Segment::track([
            'userId' => (string) auth()->id(),
            'event' => 'tenant_desk_transferred',
            'properties' => [
                'tenant_uid' => tenant('id'),
                'tenant_name' => tenant('name'),
                'tenant_desk_uid' => (string) $from->id,
            ],
            'context' => [
                'groupId' => tenant('id'),
            ],
        ]);

        $builder = new ReleaseEventsBuilder();

        $builder->handle(
            'desk:transfer',
            [
                'from' => $from->getKey(),
                'to' => $args['to_id'],
                'trash' => $trash,
            ],
        );

        return true;
    }
}
