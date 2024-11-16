<?php

namespace App\Console\Commands\Tenants;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use stdClass;
use Webmozart\Assert\Assert;

class CalculateArticleCorrelation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'article:correlation {tenant? : tenant id} {article? : article id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate correlation for articles';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        Assert::nullOrStringNotEmpty($tenantId);

        if ($tenantId) {
            $tenant = Tenant::withTrashed()->find($tenantId);
            Assert::nullOrIsInstanceOf($tenant, Tenant::class);

            if ($tenant === null || $tenant->trashed()) {
                $this->error($tenantId . ' is an invalid tenant id.');

                return self::INVALID;
            }
        }

        /** @var Lock|null $lock */
        $lock = null;

        // @phpstan-ignore-next-line
        tenancy()->runForMultiple($tenantId ? [$tenant] : null, function (Tenant $tenant) use (&$lock) {
            if (!$tenant->initialized) {
                return;
            }

            $lock?->release();

            $articleId = $this->argument('article');

            $lock = Cache::lock(
                sprintf(
                    'article-correlation-%s-%s',
                    $tenant->id,
                    is_numeric($articleId) ? $articleId : 'null',
                ),
                300,
            );

            if (!$lock->get()) {
                return;
            }

            $query = DB::table('articles')
                ->select(['id', 'title', 'plaintext']);

            if ($articleId) {
                $query->where('id', '=', $articleId);
            }

            $total = $query->count();

            if ($total === 0) {
                return;
            }

            $this->info(sprintf('Processing tenant %s...', $tenant->id));

            $bar = $this->output->createProgressBar($total);

            $bar->start();

            /** @var stdClass $article */
            foreach ($query->lazyById(100) as $article) {
                $bar->advance();

                $title = mb_strtolower(
                    trim(
                        strip_tags($article->title ?: ''),
                    ),
                );

                $body = mb_strtolower(
                    trim($article->plaintext ?: ''),
                );

                if (empty($title) || empty($body)) {
                    continue;
                }

                $titleSplits = mb_split('\s*\W+\s*', $title);

                $bodySplits = mb_split('\s*\W+\s*', $body);

                if ($titleSplits === false || $bodySplits === false) {
                    continue;
                }

                $titleTokens = array_unique(
                    array_filter(
                        $titleSplits,
                        fn (string $token) => mb_strlen($token) > 2 && !is_numeric($token),
                    ),
                );

                $bodyTokens = array_diff(
                    array_filter(
                        $bodySplits,
                        fn (string $token) => mb_strlen($token) > 3 && !is_numeric($token),
                    ),
                    $this->ignores(),
                );

                if (empty($titleTokens) || empty($bodyTokens)) {
                    continue;
                }

                $counts = array_count_values($bodyTokens);

                arsort($counts);

                $keywords = array_keys(
                    array_slice($counts, 0, 20),
                );

                $titleKeyword = implode(' ', $titleTokens);

                $bodyKeyword = implode(' ', $keywords);

                /** @var Collection<int, stdClass> $scores */
                $scores = DB::table('articles')
                    ->select(['id'])
                    ->selectRaw('FLOOR( match (`title`) against (?) * 1000 + match (`plaintext`) against (?) * 400 ) AS `score`', [$titleKeyword, $bodyKeyword])
                    ->where('id', '!=', $article->id)
                    ->whereRaw('FLOOR( match (`title`) against (?) * 1000 + match (`plaintext`) against (?) * 400 ) > 0', [$titleKeyword, $bodyKeyword])
                    ->orderByDesc('score')
                    ->take(10)
                    ->get();

                foreach ($scores as $score) {
                    DB::table('article_correlation')->updateOrInsert([
                        'source_id' => $article->id,
                        'target_id' => $score->id,
                    ], [
                        'correlation' => $score->score,
                    ]);
                }
            }

            $lock->release();

            $bar->finish();

            $this->newLine();
        });

        $lock?->release();

        return self::SUCCESS;
    }

    /**
     * Ignored tokens.
     *
     * @return string[]
     */
    protected function ignores(): array
    {
        return [
            'about',
            'after',
            'almost',
            'along',
            'also',
            'another',
            'area',
            'around',
            'available',
            'back',
            'because',
            'been',
            'being',
            'best',
            'better',
            'came',
            'capable',
            'control',
            'could',
            'course',
            'decided',
            'didn',
            'different',
            'doesn',
            'down',
            'drive',
            'each',
            'easily',
            'easy',
            'edition',
            'enough',
            'even',
            'every',
            'example',
            'find',
            'first',
            'found',
            'from',
            'going',
            'good',
            'hard',
            'have',
            'here',
            'into',
            'just',
            'know',
            'last',
            'left',
            'like',
            'little',
            'long',
            'look',
            'made',
            'make',
            'many',
            'menu',
            'might',
            'more',
            'most',
            'much',
            'name',
            'nbsp',
            'need',
            'number',
            'only',
            'original',
            'other',
            'over',
            'part',
            'place',
            'point',
            'pretty',
            'probably',
            'problem',
            'quite',
            'quot',
            'really',
            'results',
            'right',
            'same',
            'several',
            'sherree',
            'should',
            'since',
            'size',
            'small',
            'some',
            'something',
            'special',
            'still',
            'stuff',
            'such',
            'sure',
            'system',
            'take',
            'than',
            'that',
            'their',
            'them',
            'then',
            'there',
            'these',
            'they',
            'thing',
            'things',
            'think',
            'this',
            'those',
            'though',
            'through',
            'time',
            'today',
            'together',
            'took',
            'used',
            'using',
            'very',
            'want',
            'well',
            'went',
            'were',
            'what',
            'when',
            'where',
            'which',
            'while',
            'white',
            'will',
            'with',
            'would',
            'your',
        ];
    }
}
