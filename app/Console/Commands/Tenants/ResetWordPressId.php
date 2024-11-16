<?php

namespace App\Console\Commands\Tenants;

use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Integrations\WordPress;
use Illuminate\Console\Command;
use Storipress\WordPress\Exceptions\InvalidPostIdException;

class ResetWordPressId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wordpress:reset {--tenants=*}';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenants = Tenant::withoutEagerLoads()
            ->withTrashed()
            ->initialized();

        if (! empty($this->option('tenants'))) {
            $tenants->whereIn('id', $this->option('tenants'));
        }

        runForTenants(function (Tenant $tenant) {
            $wordpress = WordPress::retrieve();

            if (! $wordpress->is_connected) {
                return;
            }

            $articles = Article::withTrashed()
                ->withoutEagerLoads()
                ->whereNotNull('wordpress_id')
                ->lazyById();

            foreach ($articles as $article) {
                try {
                    if (! is_int($article->wordpress_id)) {
                        continue;
                    }

                    app('wordpress')
                        ->post()
                        ->retrieve($article->wordpress_id);
                } catch (InvalidPostIdException $e) {
                    $article->update([
                        'wordpress_id' => null,
                    ]);
                }
            }

        }, $tenants->lazyById(50));

        return static::SUCCESS;
    }
}
