<?php

namespace App\Http\Controllers\Partners\Zapier;

use App\Enums\Article\PublishType;
use App\Events\Entity\Article\ArticleUnpublished;
use App\Exceptions\ErrorCode;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\UserActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UnpublishArticleController extends ZapierController
{
    protected string $topic = 'article.unpublish';

    public function __invoke(Request $request): JsonResponse
    {
        $tenant = auth()->user();

        if (!$tenant instanceof Tenant) {
            // unauthorized
            return $this->failed(ErrorCode::ZAPIER_MISSING_CLIENT, 401);
        }

        $validator = Validator::make($request->all(), [
            'topic' => 'required|string',
            'id' => 'nullable|required_without_all:sid,slug|int',
            'sid' => 'nullable|required_without_all:id,slug|string',
            'slug' => 'nullable|required_without_all:id,sid|string',
        ]);

        if ($validator->fails()) {
            return $this->failed(ErrorCode::ZAPIER_INVALID_PAYLOAD, 400);
        }

        // validate topic
        if ($request->input('topic') !== $this->topic) {
            return $this->failed(ErrorCode::ZAPIER_INVALID_TOPIC, 400);
        }

        $data = $tenant->run(function () use ($request, $tenant) {
            $article = null;
            $key = '';

            if ($id = $request->input('id')) {
                $article = Article::find($id);

                $key = 'id';
            } elseif ($sid = $request->input('sid')) {
                /** @var string $sid */
                $article = Article::sid($sid)->first();

                $key = 'sid';
            } elseif ($slug = $request->input('slug')) {
                $article = Article::where('slug', $slug)->first();

                $key = 'slug';
            }

            if (!($article instanceof Article)) {
                return $this->failed(ErrorCode::ZAPIER_ARTICLE_NOT_FOUND, 404, ['key' => $key]);
            }

            $article->published_at = null;

            $article->publish_type = PublishType::none();

            $article->save();

            $owner = $tenant->owner;

            ArticleUnpublished::dispatch($tenant->id, $article->id);

            UserActivity::log(
                name: 'article.unschedule',
                subject: $article,
                data: [
                    'from' => 'zapier',
                ],
                userId: $owner->id,
            );

            return $article->toWebhookArray();
        });

        if ($data instanceof JsonResponse) {
            return $data;
        }

        return response()->json($data);
    }
}
