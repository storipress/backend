<?php

namespace App\Console\Schedules\Weekly;

use App\Console\Schedules\Command;
use App\Enums\CustomDomain\Group;
use App\Models\CustomDomain;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

class RevokeInvalidPostmarkRecord extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $signature = 'revoke-invalid-postmark-record {--dry-run}';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $simulation = $this->option('dry-run');

        if (! $simulation && ! app()->isProduction()) {
            return static::SUCCESS;
        }

        $postmark = app('postmark.account');

        $deadline = now()->endOfDay()->subDays(15);

        $limit = 500;

        $offset = 0;

        $domains = [];

        while (true) {
            $data = $postmark->listDomains($limit, $offset);

            foreach ($data->getDomains() as $domain) {
                $domains[] = (array) $domain;
            }

            if (($limit + $offset) >= $data->TotalCount) {
                break;
            }

            $offset += $limit;
        }

        foreach ($domains as $domain) {
            if ($domain['DKIMVerified'] && $domain['ReturnPathDomainVerified']) {
                continue;
            }

            $id = $domain['ID'];

            $name = $domain['Name'];

            if (in_array($name, ['storipress.com', 'storipress.xyz'])) {
                continue;
            }

            $tenant = Tenant::withTrashed()
                ->withoutEagerLoads()
                ->where(function (Builder $query) use ($id, $name) {
                    $query->whereJsonContains('data->postmark_id', $id)
                        ->orWhereJsonContains('data->postmark->id', $id)
                        ->orWhereJsonContains('data->mail_domain', $name);
                })
                ->first();

            $records = CustomDomain::query()
                ->withoutEagerLoads()
                ->where('group', '=', Group::mail())
                ->where('domain', '=', $name)
                ->get();

            $message = sprintf(
                'deleting %s (https://account.postmarkapp.com/signature_domains/%d)',
                $name,
                $id,
            );

            if (! ($tenant instanceof Tenant)) {
                if ($simulation) {
                    $this->info($message);
                } else {
                    if ($records->isNotEmpty()) {
                        $this->warn('Domain is not linked to tenant: %s', $records->toJson());
                    } else {
                        $this->info($message);

                        $postmark->deleteDomain($id);
                    }
                }

                continue;
            }

            if ($records->isEmpty()) {
                if ($simulation) {
                    $this->warn(
                        sprintf(
                            'Domain is not linked to tenant: %s - %s',
                            $tenant->id,
                            $name,
                        ),
                    );

                    $this->info($message);
                } else {
                    $this->info($message);

                    $postmark->deleteDomain($id);

                    $tenant->update([
                        'postmark_id' => null,
                        'postmark' => null,
                        'mail_domain' => null,
                    ]);
                }
            } else {
                if ($records->first()?->created_at->greaterThanOrEqualTo($deadline)) {
                    continue;
                }

                if ($simulation) {
                    $this->info($message);
                } else {
                    $this->info($message);

                    $postmark->deleteDomain($id);

                    foreach ($records as $record) {
                        $record->delete();
                    }

                    $tenant->update([
                        'postmark_id' => null,
                        'postmark' => null,
                        'mail_domain' => null,
                    ]);
                }
            }
        }

        return static::SUCCESS;
    }
}
