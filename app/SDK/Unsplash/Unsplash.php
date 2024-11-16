<?php

namespace App\SDK\Unsplash;

use Exception;
use Unsplash\ArrayObject;
use Unsplash\Exception as UnsplashException;
use Unsplash\HttpClient;
use Unsplash\PageResult;
use Unsplash\Photo;
use Unsplash\Search;

class Unsplash
{
    /**
     * Unsplash constructor.
     */
    public function __construct(string $key)
    {
        HttpClient::init([
            'applicationId' => $key,
            'utmSource' => 'Storipress',
        ]);
    }

    /**
     * Retrieve all the photos on a specific page.
     *
     * @return ArrayObject<mixed>
     *
     * @throws Exception
     */
    public function list(int $page = 1): ArrayObject
    {
        try {
            return Photo::all($page, 30);
        } catch (UnsplashException) {
            return new ArrayObject([], []);
        }
    }

    /**
     * Retrieve a single page of photo results
     * depending on search results.
     *
     *
     * @throws Exception
     */
    public function search(string $keyword, int $page = 1, string $orientation = 'landscape'): PageResult
    {
        try {
            return Search::photos($keyword, $page, 30, $orientation);
        } catch (UnsplashException) {
            return new PageResult([], 0, 0, [], Photo::class);
        }
    }

    /**
     * Triggers a download for a photo.
     *
     *
     * @throws Exception
     */
    public function download(string $id): string
    {
        try {
            return Photo::find($id)->download();
        } catch (UnsplashException) {
            return 'https://storipress.com';
        }
    }
}
