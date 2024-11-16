<?php

namespace App\Http\Controllers\Partners\Zapier;

use App\Exceptions\ErrorCode;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WebhookPerformController extends ZapierController
{
    /**
     * redirect to authorize url
     */
    public function __invoke(Request $request): JsonResponse
    {
        $tenant = auth()->user();

        if (! $tenant instanceof Tenant) {
            // unauthorized
            return $this->failed(ErrorCode::ZAPIER_MISSING_CLIENT, 401);
        }

        $validator = Validator::make($request->all(), [
            'topic' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->failed(ErrorCode::ZAPIER_INVALID_PAYLOAD, 400);
        }

        /** @var string $topic */
        $topic = $request->input('topic');

        if (! $this->validate($topic)) {
            return $this->failed(ErrorCode::ZAPIER_INVALID_TOPIC, 400);
        }

        $perform = $tenant->run(fn () => $this->perform($topic));

        return response()->json([$perform]);
    }

    /**
     * @return array<mixed>
     */
    protected function perform(string $topic): ?array
    {
        $article = $this->topics[$topic]::first();

        $group = Str::before($topic, '.');

        return $article ? [
            'type' => $topic,
            'data' => $article->toWebhookArray(),
            'created_at' => now()->timestamp,
        ] : [
            'type' => $topic,
            'data' => $this->{$group}(),
            'created_at' => now()->timestamp,
        ];
    }

    /**
     * @return array<mixed>
     */
    protected function article(): array
    {
        return [
            'id' => 1,
            'desk' => [
                'id' => 1,
                'name' => 'Desk Name',
            ],
            'stage' => [
                'id' => 1,
                'name' => 'Draft',
            ],
            'url' => 'https://storipress.com/article-slug',
            'title' => 'This is an article title',
            'slug' => 'article-slug',
            'featured' => true,
            'blurb' => 'This is an article blurb',
            'cover' => 'https://storipress.com/cover.jpg',
            'authors' => [
                [
                    'id' => 1,
                    'full_name' => 'Full Name',
                    'avatar' => 'https://storipress.com/avatar.jpg',
                ],
            ],
            'tags' => [
                [
                    'id' => 1,
                    'name' => 'Tag Name',
                ],
            ],
            'published' => true,
            'published_at' => now()->timestamp,
            'created_at' => now()->timestamp,
            'updated_at' => now()->timestamp,
        ];
    }

    /**
     * @return array<mixed>
     */
    protected function subscriber(): array
    {
        return [
            'id' => 1,
            'email' => 'test@storipress.com',
            'full_name' => 'Test User',
            'activity' => 1,
            'subscribed_at' => now()->timestamp,
        ];
    }
}
