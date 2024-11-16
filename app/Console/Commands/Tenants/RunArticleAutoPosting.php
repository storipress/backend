<?php

namespace App\Console\Commands\Tenants;

use App\Enums\AutoPosting\State;
use App\Jobs\Integration\AutoPost;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\ArticleAutoPosting;
use App\Models\Tenants\Integration;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class RunArticleAutoPosting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'article:auto-post';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run article auto posting';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        tenancy()->runForMultiple(
            null,
            function (Tenant $tenant) {
                if (!$tenant->initialized) {
                    return;
                }

                /** @var Collection<int, ArticleAutoPosting> $autoPostings */
                $autoPostings = ArticleAutoPosting::where('state', State::initialized())
                    ->where('scheduled_at', '<=', now())
                    ->get();

                if ($autoPostings->isEmpty()) {
                    return;
                }

                $activated = $this->getActivatedIntegration();

                /** @var string $key */
                $key = $tenant->getKey();

                foreach ($autoPostings as $autoPosting) {
                    /** @var Article $article */
                    $article = $autoPosting->article()->first();

                    if (!$article->published || !in_array($autoPosting->platform, $activated)) {
                        $autoPosting->update([
                            'state' => State::cancelled(),
                            'data' => [
                                'message' => (!$article->published)
                                    ? 'Article is not published.'
                                    : 'Integration is not activated.',
                            ],
                        ]);

                        continue;
                    }

                    $autoPosting->update([
                        'state' => State::waiting(),
                    ]);

                    AutoPost::dispatch($key, $autoPosting->article_id, $autoPosting->platform);
                }
            },
        );

        return self::SUCCESS;
    }

    /**
     * get active integration.
     *
     * @return string[]
     */
    protected function getActivatedIntegration(): array
    {
        /** @var string[] $keys */
        $keys = Integration::activated()
            ->whereNotNull('data')
            ->pluck('key')
            ->all();

        return $keys;
    }
}
