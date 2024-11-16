<?php

namespace App\GraphQL\Mutations\Site;

use App\Exceptions\BadRequestHttpException;
use App\GraphQL\Mutations\Mutation;
use App\GraphQL\Traits\S3UploadHelper;
use App\Jobs\ImportContentFromOtherCMS;
use App\Models\Tenants\UserActivity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

final class ImportSiteContentFromWordPress extends Mutation
{
    use S3UploadHelper;

    /**
     * @param  array{
     *     file?: UploadedFile,
     *     key?: string,
     *     signature?: string,
     * }  $args
     */
    public function __invoke($_, array $args): bool
    {
        $badRequest = new BadRequestHttpException();

        $fromS3 = false;

        if (isset($args['file'])) {
            $file = $args['file'];

            if (!$this->decompressGzip($file->path())) {
                throw $badRequest;
            }

            $line = $file->openFile()->fgets();
        } elseif (isset($args['key']) && isset($args['signature'])) {
            $fromS3 = true;

            $local = $this->s3ToLocal($args['key'], $args['signature']);

            if (!$this->decompressGzip($local)) {
                throw $badRequest;
            }

            $fp = fopen($local, 'r');

            if (!$fp || !($line = fgets($fp))) {
                throw $badRequest;
            }

            fclose($fp);
        } else {
            throw $badRequest;
        }

        if (empty($line) || empty($line = trim($line))) {
            throw $badRequest;
        }

        if (!is_array($meta = json_decode($line, true))) {
            throw $badRequest;
        }

        if (!isset($meta['version'], $meta['type'])) {
            throw $badRequest;
        }

        $client = tenant_or_fail()->id;

        if ($fromS3) {
            $path = $local;
        } else {
            $temp = temp_file();

            $path = $file->move(dirname($temp), basename($temp))->getPathname();
        }

        $name = sprintf(
            'wordpress-export-%s-%d.ndjson',
            $client,
            now()->timestamp,
        );

        Storage::drive('nfs')->putFileAs('/', $path, $name);

        tenancy()->central(
            fn () => ImportContentFromOtherCMS::dispatch($client, $name),
        );

        UserActivity::log(
            name: 'publication.import',
            data: [
                'from' => 'wordpress',
            ],
        );

        return true;
    }

    protected function decompressGzip(string $path): bool
    {
        $test = new Process(['gzip', '--decompress', '--test', $path]);

        $code = $test->run();

        if ($code !== 0) {
            return true;
        }

        $gzip = sprintf('%s.gz', $path);

        if (!copy($path, $gzip)) {
            return false;
        }

        $unzip = new Process([
            'gzip',
            '--decompress',
            '--force',
            $gzip,
        ]);

        return $unzip->run() === 0;
    }
}
