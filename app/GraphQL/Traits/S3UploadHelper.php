<?php

namespace App\GraphQL\Traits;

use App\Exceptions\NotFoundHttpException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

trait S3UploadHelper
{
    protected function s3ToLocal(string $key, string $signature): string
    {
        throw_unless(
            hash_equals(hmac([$key]), $signature),
            new NotFoundHttpException(),
        );

        $path = tenancy()->central(fn () => Cache::pull($key));

        throw_unless(is_string($path), new NotFoundHttpException());

        return tap(temp_file(), function (string $local) use ($path) {
            file_put_contents(
                $local,
                Storage::drive('s3')->readStream($path),
            );
        });
    }
}
