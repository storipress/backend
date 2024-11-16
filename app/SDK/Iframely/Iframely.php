<?php

namespace App\SDK\Iframely;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class Iframely
{
    /**
     * @var PendingRequest
     */
    protected $http;

    /**
     * Iframely constructor.
     */
    public function __construct(string $key)
    {
        $this->http = Http::baseUrl('https://iframe.ly/api/')
            ->connectTimeout(5)
            ->timeout(10)
            ->retry(3, 1000)
            ->withOptions(['query' => ['api_key' => $key]])
            ->withUserAgent('storipress/2022-05-14');
    }

    /**
     * @param  array<string, int|string>  $params
     * @return array<string, mixed>
     */
    public function iframely(string $url, array $params = []): mixed
    {
        $query = array_merge($params, [
            'url' => $url,
        ]);

        $response = $this->http->get('iframely', $query);

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return $data;
    }
}
