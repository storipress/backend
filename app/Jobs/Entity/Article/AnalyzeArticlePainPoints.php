<?php

namespace App\Jobs\Entity\Article;

use App\Enums\Analyze\Type;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Queue\Middleware\WithoutOverlapping;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class AnalyzeArticlePainPoints implements ShouldQueue
{
    use Dispatchable;
    use HasLlmEndpoint;
    use InteractsWithQueue;
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $tenantId,
        public int $articleId,
    ) {
        //
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->overlappingKey()))
                ->dontRelease(),
        ];
    }

    /**
     * The job's unique key used for preventing overlaps.
     */
    public function overlappingKey(): string
    {
        return sprintf('%s:%s', $this->tenantId, $this->articleId);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->with(['owner', 'owner.accessTokens'])
            ->initialized()
            ->find($this->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $token = $tenant->owner->accessTokens->first()?->token;

        if ($token === null) {
            return;
        }

        $tenant->run(function (Tenant $tenant) use ($token) {
            $article = Article::withoutEagerLoads()
                ->find($this->articleId);

            if (! ($article instanceof Article)) {
                return;
            }

            $content = $article->plaintext;

            if (! is_not_empty_string($content)) {
                return;
            }

            $input = [
                'company' => $tenant->name,
                'description' => $tenant->prophet_config['core_competency'] ?? '',
                'article' => $content,
            ];

            $response = app('http2')
                ->withToken($token)
                ->timeout(120)
                ->withHeaders([
                    'Origin' => rtrim(app_url('/'), '/'),
                ])
                ->post($this->llm(), [
                    'type' => 'pain-points-v2',
                    'data' => [
                        'system' => $input,
                        'human' => $input,
                    ],
                    'client_id' => $tenant->id,
                ]);

            if (! $response->ok()) {
                return;
            }

            $payload = [
                'content' => $content,
            ];

            $checksum = hmac($payload, true, 'md5');

            $article->pain_point()->updateOrCreate([
                'type' => Type::articlePainPoints(),
            ], [
                'checksum' => $checksum,
                'data' => $response->json(),
            ]);
        });
    }
}
