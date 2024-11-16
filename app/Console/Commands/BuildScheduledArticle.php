<?php

namespace App\Console\Commands;

use App\Builder\ReleaseEventsBuilder;
use App\Enums\Article\PublishType;
use App\Events\Entity\Article\ArticlePublished;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Integrations\WordPress;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

class BuildScheduledArticle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'article:scheduled:build';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Invoke generator to build scheduled articles';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->comment(
            sprintf('[%d] article:scheduled:build begin', time()),
        );

        tenancy()->end();

        $from = now()->startOfMinute()->subMinute();

        $to = $from->copy()->endOfMinute();

        $builder = new ReleaseEventsBuilder();

        tenancy()->runForMultiple(
            null,
            function (Tenant $tenant) use ($builder, $from, $to) {
                if (!$tenant->initialized) {
                    return;
                }

                if (WordPress::retrieve()->is_activated) {
                    return;
                }

                $query = Article::whereBetween('published_at', [$from, $to])
                    ->where('publish_type', PublishType::schedule());

                /** @var Collection<int, int> $ids */
                $ids = $query->pluck('id');

                if ($ids->isNotEmpty()) {
                    foreach ($ids as $id) {
                        ArticlePublished::dispatch($tenant->id, $id);
                    }

                    // @todo pass only scheduled articles
                    $release = $builder->handle('article:schedule', $ids->toArray());

                    if ($release === null) {
                        return;
                    }

                    try {
                        $query->chunk(500, fn ($articles) => $articles->searchable());
                    } catch (Throwable $e) {
                        //
                    }
                }
            },
        );

        $this->comment(
            sprintf('[%d] article:scheduled:build end', time()),
        );

        $this->info('Scheduled articles built.');

        return self::SUCCESS;
    }
}
