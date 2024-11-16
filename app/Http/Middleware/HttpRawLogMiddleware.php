<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Response;

class HttpRawLogMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        $token = config('services.logtail.token');

        if (!is_string($token) || empty($token)) {
            return;
        }

        $content = $response->getContent();

        if ($content === false) {
            return;
        }

        if (!Str::contains($content, '"errors"', true)) {
            return;
        }

        app('http')
            ->withToken($token)
            ->post('https://in.logtail.com', [
                'environment' => app()->environment(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'headers' => $this->toHeaders($request->headers),
                'user' => $request->user()?->toArray(), // @phpstan-ignore-line
                'body' => $request->toArray(),
                'response.version' => $response->getProtocolVersion(),
                'response.status' => $response->getStatusCode(),
                'response.headers' => $this->toHeaders($response->headers),
                'response.content' => $content,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function toHeaders(HeaderBag $bag): array
    {
        $headers = [];

        foreach ($bag as $name => $values) {
            $headers[$name] = Arr::first($values);
        }

        return $headers;
    }
}
