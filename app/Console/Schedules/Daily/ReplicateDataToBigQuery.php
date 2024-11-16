<?php

namespace App\Console\Schedules\Daily;

use App\Console\Schedules\Command;
use App\Models\Subscriber;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Invitation;
use App\Models\User;
use Google\Cloud\BigQuery\BigQueryClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Subscription;
use Webmozart\Assert\Assert;

/**
 * @see https://cloud.google.com/bigquery/docs/reference/rest/v2/tables#TableFieldSchema
 *
 * @phpstan-type TBigQueryTableSchema array{
 *     fields: array<int, array{
 *         name: string,
 *         type: 'STRING'|'BYTES'|'INTEGER'|'FLOAT'|'BOOLEAN'|'TIMESTAMP'|'DATE'|'TIME'|'DATETIME'|'GEOGRAPHY'|'NUMERIC'|'BIGNUMERIC'|'JSON'|'RECORD',
 *         mode: 'NULLABLE'|'REQUIRED'|'REPEATED',
 *     }>,
 * }
 */
class ReplicateDataToBigQuery extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $encodedKey = config('services.google.customer_data_platform');

        if (! is_not_empty_string($encodedKey)) {
            return static::FAILURE;
        }

        $decodedKey = base64_decode($encodedKey, true);

        if (! is_not_empty_string($decodedKey)) {
            return static::FAILURE;
        }

        $bigQuery = new BigQueryClient([
            'projectId' => 'customer-data-platform-363108',
            'keyFile' => json_decode($decodedKey, true),
        ]);

        $dataset = $bigQuery->dataset($this->dataset());

        $groups = [
            'tenants',
            'users',
            'members',
            'articles',
            'invitations',
            'subscriptions',
        ];

        foreach ($groups as $group) {
            $table = sprintf('db_%s', $group);

            $schema = sprintf('%sSchema', $group);

            Assert::true(method_exists($this, $schema));

            $data = sprintf('%sData', $group);

            Assert::true(method_exists($this, $data));

            $path = $this->{$data}();

            $fp = fopen($path, 'r');

            Assert::resource($fp);

            // @see https://cloud.google.com/bigquery/docs/reference/rest/v2/Job#jobconfigurationload
            $job = $dataset
                ->table($table)
                ->load($fp)
                // ->timePartitioning(['type' => 'DAY']) // partition by data loaded day
                ->createDisposition('CREATE_IF_NEEDED') // create table if not exists
                ->writeDisposition('WRITE_TRUNCATE') // if the table already exists, BigQuery overwrites the data, removes the constraints, and uses the schema from the query result
                ->sourceFormat('NEWLINE_DELIMITED_JSON') // source data is JSON format
                ->autodetect(false) // disable schema auto detection
                ->schema($this->{$schema}()) // schema definition
                // ->schemaUpdateOptions(['ALLOW_FIELD_ADDITION', 'ALLOW_FIELD_RELAXATION']) // allow schema update
                ->ignoreUnknownValues(true); // ignore fields that aren't in the schema definition

            $bigQuery->startJob($job);

            unlink($path);
        }

        return static::SUCCESS;
    }

    protected function dataset(): string
    {
        return match (app()->environment()) {
            'production' => 'app',
            default => 'app_development',
        };
    }

    /**
     * @return TBigQueryTableSchema
     */
    protected function tenantsSchema(): array
    {
        return [
            'fields' => [
                ['name' => 'id', 'type' => 'STRING', 'mode' => 'REQUIRED'],
                ['name' => 'user_id', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'plan', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'has_prophet', 'type' => 'BOOLEAN', 'mode' => 'NULLABLE'],
                ['name' => 'enabled', 'type' => 'BOOLEAN', 'mode' => 'NULLABLE'],
                ['name' => 'name', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'description', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'url', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'email', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'timezone', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'lang', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'socials', 'type' => 'JSON', 'mode' => 'NULLABLE'],
                ['name' => 'workspace', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'custom_domain', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'newsletter', 'type' => 'BOOLEAN', 'mode' => 'NULLABLE'],
                ['name' => 'subscription', 'type' => 'BOOLEAN', 'mode' => 'NULLABLE'],
                ['name' => 'currency', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'monthly_price', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'yearly_price', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'created_at', 'type' => 'TIMESTAMP', 'mode' => 'NULLABLE'],
                ['name' => 'updated_at', 'type' => 'TIMESTAMP', 'mode' => 'NULLABLE'],
                ['name' => 'deleted_at', 'type' => 'TIMESTAMP', 'mode' => 'NULLABLE'],
                ['name' => 'users', 'type' => 'JSON', 'mode' => 'NULLABLE'],
                ['name' => 'members', 'type' => 'JSON', 'mode' => 'NULLABLE'],
            ],
        ];
    }

    /**
     * @return TBigQueryTableSchema
     */
    protected function usersSchema(): array
    {
        return [
            'fields' => [
                ['name' => 'id', 'type' => 'STRING', 'mode' => 'REQUIRED'],
                ['name' => 'email', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'name', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'first_name', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'last_name', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'slug', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'location', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'bio', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'website', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'socials', 'type' => 'JSON', 'mode' => 'NULLABLE'],
                ['name' => 'signed_up_source', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'trial_ends_at', 'type' => 'TIMESTAMP', 'mode' => 'NULLABLE'],
                ['name' => 'verified_at', 'type' => 'TIMESTAMP', 'mode' => 'NULLABLE'],
                ['name' => 'created_at', 'type' => 'TIMESTAMP', 'mode' => 'NULLABLE'],
                ['name' => 'updated_at', 'type' => 'TIMESTAMP', 'mode' => 'NULLABLE'],
                ['name' => 'subscribed', 'type' => 'BOOLEAN', 'mode' => 'NULLABLE'],
                ['name' => 'plan', 'type' => 'STRING', 'mode' => 'NULLABLE'],
            ],
        ];
    }

    /**
     * @return TBigQueryTableSchema
     */
    protected function membersSchema(): array
    {
        return [
            'fields' => [
                ['name' => 'id', 'type' => 'STRING', 'mode' => 'REQUIRED'],
                ['name' => 'email', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'name', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'first_name', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'last_name', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'verified_at', 'type' => 'TIMESTAMP', 'mode' => 'NULLABLE'],
            ],
        ];
    }

    /**
     * @return TBigQueryTableSchema
     */
    protected function articlesSchema(): array
    {
        return [
            'fields' => [
                ['name' => 'tenant_id', 'type' => 'STRING', 'mode' => 'REQUIRED'],
                ['name' => 'id', 'type' => 'STRING', 'mode' => 'REQUIRED'],
                ['name' => 'sid', 'type' => 'STRING', 'mode' => 'REQUIRED'],
                ['name' => 'title', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'slug', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'blurb', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'featured', 'type' => 'BOOLEAN', 'mode' => 'NULLABLE'],
                ['name' => 'cover', 'type' => 'JSON', 'mode' => 'NULLABLE'],
                ['name' => 'seo', 'type' => 'JSON', 'mode' => 'NULLABLE'],
                ['name' => 'auto_posting', 'type' => 'JSON', 'mode' => 'NULLABLE'],
                ['name' => 'plan', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'newsletter_at', 'type' => 'TIMESTAMP', 'mode' => 'NULLABLE'],
                ['name' => 'published_at', 'type' => 'TIMESTAMP', 'mode' => 'NULLABLE'],
                ['name' => 'created_at', 'type' => 'TIMESTAMP', 'mode' => 'NULLABLE'],
                ['name' => 'updated_at', 'type' => 'TIMESTAMP', 'mode' => 'NULLABLE'],
                ['name' => 'deleted_at', 'type' => 'TIMESTAMP', 'mode' => 'NULLABLE'],
                ['name' => 'authors', 'type' => 'JSON', 'mode' => 'NULLABLE'],
            ],
        ];
    }

    /**
     * @return TBigQueryTableSchema
     */
    protected function invitationsSchema(): array
    {
        return [
            'fields' => [
                ['name' => 'tenant_id', 'type' => 'STRING', 'mode' => 'REQUIRED'],
                ['name' => 'id', 'type' => 'STRING', 'mode' => 'REQUIRED'],
                ['name' => 'inviter_id', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'email', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'role', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'created_at', 'type' => 'TIMESTAMP', 'mode' => 'NULLABLE'],
                ['name' => 'deleted_at', 'type' => 'TIMESTAMP', 'mode' => 'NULLABLE'],
            ],
        ];
    }

    /**
     * @return TBigQueryTableSchema
     */
    protected function subscriptionsSchema(): array
    {
        return [
            'fields' => [
                ['name' => 'id', 'type' => 'STRING', 'mode' => 'REQUIRED'],
                ['name' => 'user_id', 'type' => 'STRING', 'mode' => 'REQUIRED'],
                ['name' => 'name', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'stripe_id', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'stripe_status', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'stripe_price', 'type' => 'STRING', 'mode' => 'NULLABLE'],
                ['name' => 'quantity', 'type' => 'INTEGER', 'mode' => 'NULLABLE'],
                ['name' => 'trial_ends_at', 'type' => 'TIMESTAMP', 'mode' => 'NULLABLE'],
                ['name' => 'ends_at', 'type' => 'TIMESTAMP', 'mode' => 'NULLABLE'],
                ['name' => 'created_at', 'type' => 'TIMESTAMP', 'mode' => 'NULLABLE'],
                ['name' => 'updated_at', 'type' => 'TIMESTAMP', 'mode' => 'NULLABLE'],
            ],
        ];
    }

    protected function tenantsData(): string
    {
        $model = (new Tenant())->with(['users', 'subscribers']);

        return $this->data($model, 'tenantsSchema', function (Tenant $tenant) {
            return [
                'users' => $tenant->users()->pluck('users.id')->map(fn (int $id) => strval($id))->toArray(),
                'members' => $tenant->subscribers()->pluck('subscribers.id')->map(fn (int $id) => strval($id))->toArray(),
            ];
        });
    }

    protected function usersData(): string
    {
        $model = (new User())->with(['subscriptions']);

        return $this->data($model, 'usersSchema', function (User $user) {
            return [
                'subscribed' => $subscribed = $user->subscribed(),
                'plan' => $subscribed ? $user->subscription()?->stripe_price : null,
            ];
        });
    }

    protected function membersData(): string
    {
        return $this->data(new Subscriber(), 'membersSchema');
    }

    protected function articlesData(): string
    {
        return tap(temp_file(), function (string $path) {
            foreach (Tenant::initialized()->lazyById(50) as $tenant) {
                $tenant->run(fn () => $this->data(
                    (new Article())->with(['authors']),
                    'articlesSchema',
                    fn (Article $article) => [
                        'authors' => $article->authors()->pluck('users.id')->map(fn (int $id) => strval($id))->toArray(),
                        'tenant_id' => $tenant->id,
                    ],
                    $path,
                ));
            }
        });
    }

    protected function invitationsData(): string
    {
        return tap(temp_file(), function (string $path) {
            foreach (Tenant::initialized()->lazyById(50) as $tenant) {
                $tenant->run(fn () => $this->data(
                    new Invitation(),
                    'invitationsSchema',
                    fn () => [
                        'tenant_id' => $tenant->id,
                    ],
                    $path,
                ));
            }
        });
    }

    protected function subscriptionsData(): string
    {
        return $this->data(new Subscription(), 'subscriptionsSchema');
    }

    /**
     * @template TModel of \App\Models\Entity|\App\Models\Tenants\Entity|Tenant
     *
     * @param  Model|Builder<TModel>  $model
     */
    protected function data(Model|Builder $model, string $schema, ?callable $merge = null, ?string $path = null): string
    {
        $query = $model->withoutEagerLoads();

        if (method_exists($query, 'withTrashed')) {
            $query = $query->withTrashed();
        }

        $items = $query->lazyById(50);

        $fields = $this->{$schema}()['fields'];

        $keys = array_column($fields, 'name');

        $path = $path ?: temp_file();

        foreach ($items as $item) {
            $data = [];

            foreach ($keys as $key) {
                $data[$key] = $item->getAttributeValue($key);
            }

            if (is_callable($merge)) {
                $data = array_merge($data, $merge($item));
            }

            file_put_contents(
                $path,
                json_encode($data).PHP_EOL,
                FILE_APPEND | LOCK_EX,
            );
        }

        return $path;
    }
}
