<?php

namespace App\GraphQL\Mutations\Block;

use App\Events\Entity\Block\BlockUpdated;
use App\Exceptions\BadRequestHttpException;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Traits\S3UploadHelper;
use App\Models\Tenants\Block;
use App\Models\Tenants\UserActivity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class UpdateBlock extends BlockMutation
{
    use S3UploadHelper;

    /**
     * @param  array{
     *     id: string,
     *     file?: UploadedFile,
     *     key?: string,
     *     signature?: string,
     * }  $args
     */
    public function __invoke($_, array $args): Block
    {
        $tenant = tenant_or_fail();

        $block = Block::find($args['id']);

        if (!($block instanceof Block)) {
            throw new NotFoundHttpException();
        }

        $this->uuid = $block->uuid;

        $this->tmp = storage_path($this->uuid);

        File::ensureDirectoryExists($this->tmp);

        if (isset($args['file'])) {
            $path = $args['file']->getPathname();
        } elseif (isset($args['key']) && isset($args['signature'])) {
            $path = $this->s3ToLocal($args['key'], $args['signature']);
        } else {
            throw new BadRequestHttpException();
        }

        $this->extract($path);

        $this->upload();

        File::deleteDirectory($this->tmp);

        BlockUpdated::dispatch($tenant->id, $block->id, []);

        UserActivity::log(
            name: 'block.update',
            subject: $block,
        );

        return $block;
    }
}
