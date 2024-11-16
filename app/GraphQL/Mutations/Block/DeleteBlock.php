<?php

namespace App\GraphQL\Mutations\Block;

use App\Events\Entity\Block\BlockDeleted;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenants\Block;
use App\Models\Tenants\UserActivity;

class DeleteBlock extends BlockMutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Block
    {
        $tenant = tenant_or_fail();

        $block = Block::find($args['id']);

        if (!($block instanceof Block)) {
            throw new NotFoundHttpException();
        }

        $block->delete();

        BlockDeleted::dispatch($tenant->id, $block->id);

        UserActivity::log(
            name: 'block.delete',
            subject: $block,
        );

        return $block;
    }
}
