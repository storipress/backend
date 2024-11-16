<?php

namespace App\GraphQL\Mutations\Subscriber;

use App\Enums\Subscription\Setup;
use App\Exceptions\BadRequestHttpException;
use App\GraphQL\Traits\S3UploadHelper;
use App\Jobs\Subscriber\ImportSubscribersFromCsvFile as ImportSubscribersFromFile;
use App\Models\Tenants\UserActivity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportSubscribersFromCsvFile
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
        if (isset($args['file'])) {
            $temp = temp_file();

            $path = $args['file']->move(dirname($temp), basename($temp))->getPathname();
        } elseif (isset($args['key']) && isset($args['signature'])) {
            $path = $this->s3ToLocal($args['key'], $args['signature']);
        } else {
            throw new BadRequestHttpException();
        }

        if (! $this->whetherCSVHasEmailField($path)) {
            throw new BadRequestHttpException();
        }

        $tenant = tenant_or_fail();

        $name = sprintf(
            'subscriber-import-%s-%d.csv',
            $tenant->id,
            now()->timestamp,
        );

        Storage::drive('nfs')->putFileAs('/', $path, $name);

        ImportSubscribersFromFile::dispatch($tenant->id, $name);

        if (Setup::waitImport()->is($tenant->subscription_setup)) {
            $tenant->update([
                'subscription_setup' => Setup::waitNextStage(),
            ]);
        }

        UserActivity::log(
            name: 'member.import',
        );

        return true;
    }

    /**
     * Determinate the csv file has email field on the header or not.
     */
    protected function whetherCSVHasEmailField(string $file): bool
    {
        $fp = fopen($file, 'r');

        if ($fp === false) {
            return false;
        }

        $headers = fgets($fp);

        if ($headers === false) {
            return false;
        }

        fclose($fp);

        return Str::contains($headers, 'email', true);
    }
}
