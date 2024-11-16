<?php

namespace App\GraphQL\Mutations\Upload;

use App\Exceptions\NotFoundHttpException;
use App\Models\Tenants\Block;
use Illuminate\Http\UploadedFile;

final class UploadBlockPreview extends UploadMutation
{
    protected Block $block;

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): string
    {
        /** @var Block|null $block */
        $block = Block::find($args['id']);

        if (is_null($block)) {
            throw new NotFoundHttpException();
        }

        $this->block = $block;

        /** @var UploadedFile $file */
        $file = $args['file'];

        $path = $this->upload($file);

        $this->block->preview()->delete();

        $this->block->preview()->create(
            $this->getImageAttributes($path, $file),
        );

        return $path;
    }

    /**
     * {@inheritDoc}
     */
    protected function group(): string
    {
        return 'blocks';
    }

    /**
     * {@inheritDoc}
     */
    protected function directory(): ?string
    {
        return $this->block->uuid;
    }
}
