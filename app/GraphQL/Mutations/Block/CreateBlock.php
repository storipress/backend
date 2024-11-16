<?php

namespace App\GraphQL\Mutations\Block;

use App\Events\Entity\Block\BlockCreated;
use App\Exceptions\BadRequestHttpException;
use App\GraphQL\Traits\S3UploadHelper;
use App\Models\Tenants\Block;
use App\Models\Tenants\UserActivity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CreateBlock extends BlockMutation
{
    use S3UploadHelper;

    /**
     * @param  array{
     *     file?: UploadedFile,
     *     key?: string,
     *     signature?: string,
     * }  $args
     */
    public function __invoke($_, array $args): Block
    {
        $tenant = tenant_or_fail();

        $this->uuid = Str::uuid()->toString();

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

        $block = Block::create(['uuid' => $this->uuid]);

        BlockCreated::dispatch($tenant->id, $block->id);

        UserActivity::log(
            name: 'block.create',
            subject: $block,
        );

        return $block;
    }
}
