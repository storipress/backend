<?php

namespace App\AutoPosting\LinkedIn;

use App\AutoPosting\Dispatcher;
use App\AutoPosting\Layers\PartnerContentSyncingLayer as BaseLayer;
use App\Exceptions\ErrorCode;
use App\Exceptions\ErrorException;
use App\Resources\Partners\LinkedIn\Article;
use App\SDK\LinkedIn\LinkedIn;
use Illuminate\Support\Arr;

class PartnerContentSyncingLayer extends BaseLayer
{
    use HasFailedHandler;
    use HasStoppedHandler;

    public function __construct(protected LinkedIn $app)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @param  array{access_token: string, image_id?: string}  $data
     * @return array{post_id: string}
     *
     * @throws ErrorException
     */
    public function handle(Dispatcher $dispatcher, array $data, array $extra): array
    {
        $accessToken = $data['access_token'];

        /** @var array{text: string, enable: bool, author_id: string, scheduled_at: string} $linkedin */
        $linkedin = $dispatcher->article->linkedin;

        $authorId = $linkedin['author_id'];

        $text = $linkedin['text'];

        $imageId = $data['image_id'] ?? null;

        $url = $dispatcher->article->url;

        $seo = $dispatcher->article->seo ?: [];

        $title = Arr::get($seo, 'og.title');

        if (!is_not_empty_string($title)) {
            $title = strip_tags($dispatcher->article->title);
        }

        $article = new Article(
            author: $authorId,
            title: $title,
            text: $text,
            link: $url,
            image: $imageId,
        );

        $postId = $this->app->createPost($accessToken, $article);

        if ($postId === false) {
            throw new ErrorException(ErrorCode::LINKEDIN_POSTING_FAILED);
        }

        return [
            'post_id' => $postId,
        ];
    }
}
