<?php

namespace App\GraphQL\Mutations\Upload;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;

final class UploadSiteLogo extends UploadMutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): string
    {
        $this->authorize('write', Tenant::class);

        /** @var UploadedFile $file */
        $file = $args['file'];

        $path = $this->upload($file);

        /** @var Tenant $tenant */
        $tenant = tenant();

        $tenant->logo()->delete();

        $tenant->logo()->create($this->getImageAttributes($path, $file));

        return $path;
    }

    /**
     * {@inheritDoc}
     */
    protected function group(): string
    {
        return 'logo';
    }

    /**
     * {@inheritDoc}
     */
    protected function directory(): ?string
    {
        return null;
    }
}
