<?php

namespace App\Http\Controllers\Partners\Zapier;

use App\Builder\ReleaseEventsBuilder;
use App\Enums\Article\PublishType;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Partners\RudderStackHelper;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Stage;
use App\Models\Tenants\Tag;
use App\Models\User;
use App\Sluggable;
use Cviebrock\EloquentSluggable\Services\SlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Webmozart\Assert\Assert;

class CreateArticleController extends ZapierController
{
    use RudderStackHelper;

    protected string $topic = 'article.create';

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
            'title' => 'required|string',
            'content' => 'required|string',
            'cover' => 'nullable|string|url',
            'desk' => 'required|string',
            'slug' => 'nullable|string|max:200',
            'blurb' => 'nullable|string',
            'published_at' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'required|string',
            'featured' => 'nullable|boolean',
            'search_title' => 'nullable|string',
            'search_description' => 'nullable|string',
            'social_title' => 'nullable|string',
            'social_description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->failed(ErrorCode::ZAPIER_INVALID_PAYLOAD, 400);
        }

        if ($request->input('topic') !== $this->topic) {
            return $this->failed(ErrorCode::ZAPIER_INVALID_TOPIC, 400);
        }

        $data = $tenant->run(function () use ($request, $tenant) {
            $prosemirror = app('prosemirror');

            /** @var string $content */
            $content = $request->input('content');

            /** @var string $title */
            $title = $request->input('title');

            /** @var string $blurb */
            $blurb = $request->input('blurb') ?: '';

            $author = $tenant->owner;

            Assert::isInstanceOf($author, User::class);

            $html = $prosemirror->rewriteHTML($content);

            Assert::notNull($html);

            $content = $prosemirror->toProseMirror($html);

            $article = new Article([
                'title' => $title,
                'blurb' => $blurb,
                'featured' => $request->input('featured') ?: false,
                'document' => [
                    'default' => $content,
                    'title' => $prosemirror->toProseMirror($title),
                    'blurb' => $prosemirror->toProseMirror($blurb),
                    'annotations' => [],
                ],
                'html' => $html,
                'plaintext' => $prosemirror->toPlainText($content ?: []),
                'seo' => $this->getSeoData($request),
                'encryption_key' => base64_encode(random_bytes(32)),
            ]);

            if ($slug = $request->input('slug')) {
                /** @var string $slug */
                $article->slug = SlugService::createSlug(Article::class, 'slug', Sluggable::slug($slug));
            }

            if ($cover = $request->input('cover')) {
                /** @var string $cover */
                $article->cover = [
                    'alt' => '',
                    'caption' => '',
                    'url' => $cover,
                ];
            }

            $deskName = $request->input('desk');

            $article->desk()->associate(Desk::firstOrCreate(['name' => $deskName]));

            if ($time = $request->input('published_at')) {
                /** @var string $time */
                if (is_numeric($time)) {
                    $time = '@'.$time;
                }

                $publishedAt = Carbon::parse($time);

                $publishType = $publishedAt->isPast() ? PublishType::immediate() : PublishType::schedule();

                $article->published_at = $publishedAt->timestamp; // @phpstan-ignore-line

                $article->publish_type = $publishType;

                $article->stage()->associate(Stage::ready()->sole());
            } else {
                $article->stage()->associate(Stage::default()->sole());
            }

            $article->save();

            $this->sendRudderStackEvent('tenant_article_created', $author->id, $article->id);

            $article->authors()->attach($author);

            /** @var string[] $tags */
            $tags = $request->input('tags') ?? [];

            $tags = array_map(fn ($tag) => trim($tag), $tags);

            /**
             * unique case insensitive
             *
             * @see https://www.php.net/manual/de/function.array-unique.php#78801
             */
            $tags = array_intersect_key(
                $tags,
                array_unique(array_map(fn ($tag) => mb_strtolower($tag), $tags)),
            );

            foreach ($tags as $tag) {
                $article->tags()->attach(
                    Tag::withTrashed()->updateOrCreate(
                        ['name' => $tag],
                        ['deleted_at' => null],
                    ),
                );
            }

            // create release events
            if (PublishType::immediate()->is($publishType ?? null)) {
                $builder = new ReleaseEventsBuilder();

                $builder->handle('article:publish', ['id' => $article->id]);

                $this->sendRudderStackEvent('tenant_article_scheduled', $author->id, $article->id);
            }

            return $article->toWebhookArray();
        });

        return response()->json($data);
    }

    /**
     * @return array{og:string[], meta:string[], hasSlug:bool, ogImage:string}
     */
    protected function getSeoData(Request $request): array
    {
        return [
            'og' => [
                'title' => $this->getInput($request, 'social_title'),
                'description' => $this->getInput($request, 'social_description'),
            ],
            'meta' => [
                'title' => $this->getInput($request, 'search_title'),
                'description' => $this->getInput($request, 'search_description'),
            ],
            'hasSlug' => $request->input('slug') !== null,
            'ogImage' => '',
        ];
    }

    protected function getInput(Request $request, string $key): string
    {
        $value = $request->input($key);

        if (! is_string($value)) {
            return '';
        }

        return $value;
    }
}
