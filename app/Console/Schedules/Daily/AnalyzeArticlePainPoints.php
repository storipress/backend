<?php

namespace App\Console\Schedules\Daily;

use App\Console\Schedules\Command;
use App\Enums\Analyze\Type;
use App\Jobs\Entity\Article\AnalyzeArticlePainPoints as AnalyzeArticlePainPointsJob;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

class AnalyzeArticlePainPoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analyze:article:pain-points {--tenants=*} {--since= : Specify the start time}';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = Tenant::withoutEagerLoads()
            ->initialized();

        if (! empty($this->option('tenants'))) {
            $query->whereIn('id', $this->option('tenants'));
        }

        $tenants = $query->lazyById(50);

        $from = $this->option('since');

        $from = is_not_empty_string($from)
            ? Carbon::parse($from)->toImmutable()
            : now()->yesterday()->toImmutable();

        runForTenants(function (Tenant $tenant) use ($from) {
            if (! $tenant->has_prophet) {
                return;
            }

            $articles = Article::withoutEagerLoads()
                ->with(['pain_point' => function (MorphMany $query) {
                    $query->where('type', '=', Type::articlePainPoints());
                }])
                ->where('updated_at', '>=', $from)
                ->get();

            foreach ($articles as $article) {
                if (! is_not_empty_string($article->plaintext)) {
                    continue;
                }

                $payload = [
                    'content' => $article->plaintext,
                ];

                $analysis = $article->pain_point->first();

                $checksum = hmac($payload, true, 'md5');

                // content was not changed
                if ($analysis && hash_equals($analysis->checksum, $checksum)) {
                    continue;
                }

                AnalyzeArticlePainPointsJob::dispatchSync($tenant->id, $article->id);
            }
        }, $tenants);

        return static::SUCCESS;
    }
}
