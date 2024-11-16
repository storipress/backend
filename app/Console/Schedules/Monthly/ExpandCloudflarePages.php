<?php

namespace App\Console\Schedules\Monthly;

use App\Console\Schedules\Command;
use App\Models\CloudflarePage;
use Hashids\Hashids;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Sentry\State\Scope;

use function Sentry\captureException;
use function Sentry\withScope;

class ExpandCloudflarePages extends Command implements Isolatable
{
    /**
     * {@inheritdoc}
     */
    protected $hidden = false;

    /**
     * {@inheritdoc}
     */
    protected $signature = 'cf-pages:expand {--force}';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->option('isolated')) {
            $this->error('This command must be run in isolated mode.');

            return static::FAILURE;
        }

        $pages = CloudflarePage::withoutEagerLoads()
            ->withCount('tenants')
            ->where('occupiers', '<', CloudflarePage::MAX)
            ->get(['id', 'occupiers', 'created_at', 'updated_at']);

        $remains = $pages->sum('remains');

        if (!$this->option('force') && $remains > CloudflarePage::EXPAND) {
            return static::SUCCESS;
        }

        $nextId = $pages->max('id') + 1;

        $name = Str::lower(
            sprintf('spcs%s%s',
                Str::limit(app()->environment(), 1, ''),
                $this->hashids()->encode([
                    (string) $nextId,
                    (string) now()->timestamp,
                ]),
            ),
        );

        try {
            $data = app('cloudflare')->createPage($name);
        } catch (RequestException $e) {
            withScope(function (Scope $scope) use ($name, $e): void {
                $scope->setContext('page', [
                    'name' => $name,
                ]);

                captureException($e);
            });

            $this->error('Something went wrong when creating new page.');

            return static::FAILURE;
        }

        CloudflarePage::create([
            'id' => $nextId,
            'name' => $name,
            'raw' => $data,
        ]);

        return static::SUCCESS;
    }

    /**
     * Get hashids instance.
     */
    protected function hashids(): Hashids
    {
        return new Hashids(
            'storipress-customer-sites',
            11,
            '1234567890abcdefghijklmnopqrstuvwxyz',
        );
    }
}
