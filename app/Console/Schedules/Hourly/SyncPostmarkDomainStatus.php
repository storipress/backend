<?php

namespace App\Console\Schedules\Hourly;

use App\Console\Schedules\Command;
use App\Models\Tenant;
use Postmark\Models\PostmarkException;
use Postmark\PostmarkAdminClient;

use function Sentry\captureException;

class SyncPostmarkDomainStatus extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        return static::SUCCESS;

        // @phpstan-ignore-next-line
        $postmark = app('postmark.account');

        if (!($postmark instanceof PostmarkAdminClient)) {
            return static::SUCCESS;
        }

        $tenants = Tenant::initialized()
            ->whereNotNull('custom_domain')
            ->lazyById();

        runForTenants(function (Tenant $tenant) use ($postmark) {
            if (empty($tenant->postmark)) {
                return;
            }

            if (empty($tenant->postmark['id'])) {
                return;
            }

            if (!is_int($tenant->postmark['id'])) {
                return;
            }

            try {
                $domain = $postmark->getDomain($tenant->postmark['id']);
            } catch (PostmarkException $e) {
                // https://postmarkapp.com/developer/api/overview#error-code-510
                if ($e->postmarkApiErrorCode === 510) {
                    $tenant->update(['postmark' => null]);
                } else {
                    captureException($e);
                }

                return;
            }

            $data = [];

            foreach ($domain as $key => $value) {
                $data[$key] = $value;
            }

            if (!empty($data)) {
                $tenant->update(['postmark' => $data]);
            }
        }, $tenants);

        return static::SUCCESS;
    }
}
