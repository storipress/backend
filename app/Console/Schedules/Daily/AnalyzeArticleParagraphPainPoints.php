<?php

namespace App\Console\Schedules\Daily;

use App\Console\Schedules\Command;
use App\Enums\Analyze\Type;
use App\Jobs\Entity\Article\AnalyzeArticleParagraphPainPoints as AnalyzeArticleParagraphPainPointsJob;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AnalyzeArticleParagraphPainPoints extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $signature = 'analyze:article:paragraph:pain-points {--tenants=*} {--since= : Specify the start time}';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!app()->isLocal()) {
            return static::SUCCESS;
        }

        $query = Tenant::withoutEagerLoads()
            ->initialized();

        if (!empty($this->option('tenants'))) {
            $query->whereIn('id', $this->option('tenants'));
        }

        $tenants = $query->lazyById(50);

        $from = $this->option('since');

        $from = is_not_empty_string($from)
            ? Carbon::parse($from)->toImmutable()
            : now()->yesterday()->toImmutable();

        runForTenants(function (Tenant $tenant) use ($from) {
            $articles = Article::withoutEagerLoads()
                ->with(['pain_point' => function (MorphMany $query) {
                    $query->where('type', '=', Type::articleParagraphPainPoints());
                }])
                ->where('updated_at', '>=', $from)
                ->get();

            foreach ($articles as $article) {
                /**
                 * @var array<string, array{
                 *     type: string,
                 *     attrs?: array{
                 *         id: string
                 *     },
                 *     content: array<int, mixed>,
                 * }> $document
                 */
                $document = data_get($article->document, 'default.content', []);

                if (empty($document)) {
                    continue;
                }

                $ids = [];

                foreach ($document as $data) {
                    if ($data['type'] !== 'paragraph') {
                        continue;
                    }

                    if (empty($data['content'])) {
                        continue;
                    }

                    $uuid = data_get($data, 'attrs.id');

                    // skip data without a paragraph id
                    if (!Str::isUuid($uuid)) {
                        continue;
                    }

                    $content = $this->toPlainText($data['content']);

                    $payload = [
                        'content' => $content,
                    ];

                    $checksum = hmac($payload, true, 'md5');

                    $analysis = $article->pain_point->where('paragraph_id', '=', $uuid)->first();

                    if ($analysis && hash_equals($analysis->checksum, $checksum)) {
                        $ids[] = $uuid;

                        continue;
                    }

                    AnalyzeArticleParagraphPainPointsJob::dispatchSync(
                        $tenant->id,
                        $article->id,
                        $uuid, // @phpstan-ignore-line
                        $content,
                    );

                    $ids[] = $uuid;
                }

                $article->pain_point()
                    ->where('type', '=', Type::articleParagraphPainPoints())
                    ->whereNotIn('paragraph_id', $ids)
                    ->delete();
            }
        }, $tenants);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, mixed>  $value
     */
    public function toPlainText(array $value): string
    {
        $document = [
            'type' => 'doc',
            'content' => $value,
        ];

        return app('prosemirror')->toPlainText($document);
    }
}
