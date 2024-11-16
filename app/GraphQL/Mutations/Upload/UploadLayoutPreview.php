<?php

namespace App\GraphQL\Mutations\Upload;

use App\Exceptions\NotFoundHttpException;
use App\Models\Tenants\Layout;
use Illuminate\Http\UploadedFile;

final class UploadLayoutPreview extends UploadMutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): string
    {
        $this->authorize('write', Layout::class);

        /** @var Layout|null $layout */
        $layout = Layout::find($args['id']);

        if (is_null($layout)) {
            throw new NotFoundHttpException();
        }

        /** @var UploadedFile $file */
        $file = $args['file'];

        $path = $this->upload($file);

        $layout->preview()->delete();

        $layout->preview()->create($this->getImageAttributes($path, $file));

        return $path;
    }

    /**
     * {@inheritDoc}
     */
    protected function group(): string
    {
        return 'layouts';
    }

    /**
     * {@inheritDoc}
     */
    protected function directory(): ?string
    {
        return null;
    }
}
