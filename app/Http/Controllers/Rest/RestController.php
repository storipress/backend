<?php

namespace App\Http\Controllers\Rest;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use ReflectionClass;
use Webmozart\Assert\Assert;

abstract class RestController extends Controller
{
    protected int $ttl = 300;

    protected function getCacheKey(string $identifier): string
    {
        $hash = $this->getCurrentClassHash();

        $prefix = Str::kebab(class_basename($this));

        return sprintf(
            '%s-%s-%s',
            $prefix,
            $hash,
            $identifier,
        );
    }

    protected function getCurrentClassHash(): string
    {
        $path = (new ReflectionClass($this))->getFileName();

        Assert::stringNotEmpty($path);

        $hash = sha1_file($path);

        Assert::stringNotEmpty($hash);

        return $hash;
    }

    /**
     * @template TReturnValue
     *
     * @param  Closure(): TReturnValue  $callback
     * @return TReturnValue
     */
    protected function remember(Closure $callback, ?string $key = null, string $param = 'client'): mixed
    {
        $id = $key ?: request()->route($param, 'default');

        Assert::stringNotEmpty($id);

        return Cache::remember($this->getCacheKey($id), now()->addSeconds($this->ttl), $callback);
    }

    protected function forget(?string $key = null, string $param = 'client'): void
    {
        $id = $key ?: request()->route($param, 'default');

        Assert::stringNotEmpty($id);

        Cache::forget($this->getCacheKey($id));
    }
}
