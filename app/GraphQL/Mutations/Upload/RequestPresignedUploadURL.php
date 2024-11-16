<?php

namespace App\GraphQL\Mutations\Upload;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class RequestPresignedUploadURL
{
    /**
     * @param  array{
     *     md5?: string,
     * }  $args
     * @return array{
     *   key: string,
     *   url: string,
     *   expire_on: Carbon,
     *   signature: string,
     * }
     */
    public function __invoke($_, array $args): array
    {
        $s3 = app('aws')->createS3();

        $key = unique_token();

        $path = sprintf('tmp/%s', unique_token());

        $expireOn = now()->addHour();

        $options = [
            'Bucket' => 'storipress',
            'Key' => $path,
        ];

        if (isset($args['md5'])) {
            $options['ContentMD5'] = $args['md5'];
        }

        $request = $s3->createPresignedRequest(
            $s3->getCommand('putObject', $options),
            $expireOn->getTimestamp(),
        );

        tenancy()->central(fn () => Cache::put($key, $path, $expireOn));

        return [
            'key' => $key,
            'url' => (string) $request->getUri(),
            'expire_on' => $expireOn,
            'signature' => hmac([$key]),
        ];
    }
}
