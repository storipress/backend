<?php

namespace App\Http\Controllers\Partners\Zapier;

use App\Enums\Article\PublishType;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Partners\RudderStackHelper;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Stage;
use App\Models\Tenants\UserActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PublishArticleController extends ZapierController
{
    use RudderStackHelper;

    protected string $topic = 'article.publish';

    public function __invoke(Request $request): JsonResponse
    {
        $tenant = auth()->user();

        if (! $tenant instanceof Tenant) {
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

            if (! ($article instanceof Article)) {
                return $this->failed(ErrorCode::ZAPIER_ARTICLE_NOT_FOUND, 404, ['key' => $key]);
            }

            if ($article->published) {
                return $article->toWebhookArray();
            }

            $owner = $tenant->owner;

            $originalStageId = $article->stage_id;

            $readyStageId = Stage::ready()->first()?->id;

            if (! $readyStageId) {
                return $this->failed(ErrorCode::ZAPIER_INTERNAL_ERROR, 500);
            }

            if ($originalStageId !== $readyStageId) {
                $article->stage()->associate($readyStageId);

                UserActivity::log(
                    name: 'article.stage.change',
                    subject: $article,
                    data: [
                        'old' => $originalStageId,
                        'new' => $readyStageId,
                        'from' => 'zapier',
                    ],
                    userId: $owner->id,
                );
            }

            $time = now();

            $article->published_at = $time;

            $article->publish_type = PublishType::immediate();

            $article->save();

            UserActivity::log(
                name: 'article.schedule',
                subject: $article,
                data: [
                    'time' => $time,
                    'from' => 'zapier',
                ],
                userId: $owner->id,
            );

            $this->sendRudderStackEvent('tenant_article_scheduled', $owner->id, $article->id);

            return $article->toWebhookArray();
        });

        if ($data instanceof JsonResponse) {
            return $data;
        }

        return response()->json($data);
    }
}
