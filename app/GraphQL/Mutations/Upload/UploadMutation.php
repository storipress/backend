<?php

namespace App\GraphQL\Mutations\Upload;

use App\GraphQL\Mutations\Mutation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

abstract class UploadMutation extends Mutation
{
    protected string $token;

    /**
     * UploadMutation constructor.
     */
    public function __construct()
    {
        $this->token = unique_token();
    }

    /**
     * Upload file to AWS S3.
     */
    protected function upload(UploadedFile $file): string
    {
        $path = $this->path($file->extension());

        app('aws')->createS3()->putObject([
            'Bucket' => 'storipress',
            'Key' => sprintf('assets/%s', $path),
            'SourceFile' => $file->path(),
            'ContentType' => $file->getMimeType() ?: $file->getClientMimeType(),
        ]);

        return $path;
    }

    /**
     * Get store path.
     */
    protected function path(string $extension): string
    {
        $chunks = [
            tenant('id') ?: 'CENTRAL',
            $this->group(),
            $this->directory(),
            $this->token,
        ];

        $path = implode('/', array_values(array_filter($chunks)));

        return $path . '.' . $extension;
    }

    /**
     * Get upload group.
     */
    abstract protected function group(): string;

    /**
     * Get upload base directory.
     */
    abstract protected function directory(): ?string;

    /**
     * @return array<string, mixed>
     */
    protected function getImageAttributes(string $path, UploadedFile $file): array
    {
        $size = getimagesize($file->path());

        if ($size !== false) {
            [$width, $height] = $size;
        }

        $name = $file->getClientOriginalName();

        return [
            'token' => $this->token,
            'path' => $path,
            'title' => Str::beforeLast($name, '.'),
            'name' => $name,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => $width ?? 0,
            'height' => $height ?? 0,
        ];
    }
}
