<?php

namespace App\GraphQL\Mutations\Block;

use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveExtractionException;
use wapmorgan\UnifiedArchive\UnifiedArchive;

abstract class BlockMutation extends Mutation
{
    protected string $tmp;

    protected string $uuid;

    /**
     * @throws ArchiveExtractionException
     */
    protected function extract(string $path): void
    {
        $archive = UnifiedArchive::open($path);

        if ($archive === null) {
            throw new \RuntimeException('Cannot open upload file.');
        }

        $archive->extractFiles($this->tmp);
    }

    public function upload(): void
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        $files = File::allFiles($this->tmp);

        foreach ($files as $file) {
            $filename = trim(
                Str::remove($this->tmp, $file->getPath()),
                '/',
            );

            $path = sprintf(
                '/assets/%s/blocks/%s/%s',
                $tenant->id,
                $this->uuid,
                $filename,
            );

            Storage::disk('s3')->putFileAs(
                $path,
                $file,
                $file->getFilename(),
            );
        }
    }
}
