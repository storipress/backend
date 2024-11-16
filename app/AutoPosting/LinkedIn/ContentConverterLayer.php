<?php

namespace App\AutoPosting\LinkedIn;

use App\AutoPosting\Dispatcher;
use App\AutoPosting\Helpers\ImageDownloader;
use App\AutoPosting\Layers\ContentConverterLayer as BaseLayer;
use App\Exceptions\ErrorCode;
use App\Exceptions\ErrorException;
use App\Models\Tenants\Integration;
use App\SDK\LinkedIn\LinkedIn;

class ContentConverterLayer extends BaseLayer
{
    use HasFailedHandler;
    use HasStoppedHandler;

    public function __construct(protected LinkedIn $app) {}

    /**
     * {@inheritdoc}
     *
     * @param  array{}  $data
     * @return array{access_token: string, image_id?: string}
     *
     * @throws ErrorException
     */
    public function handle(Dispatcher $dispatcher, array $data, array $extra): array
    {
        /** @var Integration $integration */
        $integration = Integration::find('linkedin');

        /** @var array{access_token: string} $internals */
        $internals = $integration->internals;

        $accessToken = $internals['access_token'];

        /** @var array{author_id: string, text: string} $linkedin */
        $linkedin = $dispatcher->article->linkedin;

        $authorId = $linkedin['author_id'];

        $payload = [
            'access_token' => $accessToken,
        ];

        /** @var array{url: string}|null $cover */
        $cover = $dispatcher->article->cover;

        $url = $cover['url'] ?? null;

        if (empty($url)) {
            return $payload;
        }

        $path = ImageDownloader::download($url);

        $imageId = $this->app->uploadImage($accessToken, $authorId, $path);

        unlink($path);

        if ($imageId === false) {
            throw new ErrorException(ErrorCode::LINKEDIN_IMAGE_UPLOAD_FAILED);
        }

        $payload['image_id'] = $imageId;

        return $payload;
    }
}
