<?php

use App\Builder\ReleaseEventsBuilder;
use App\Models\Tenant;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redis as RedisFacade;
use Illuminate\Support\Str;
use Tuupola\Base62Proxy;
use Webmozart\Assert\Assert;

use function Sentry\captureException;

if (!function_exists('runForTenants')) {
    /**
     * Run a callback for initialized tenants.
     *
     * @param  Traversable<array-key, Tenant>|array<array-key, Tenant>|null  $iterable
     */
    function runForTenants(callable $callback, $iterable = null): void
    {
        if ($iterable !== null && empty($iterable)) {
            return;
        }

        tenancy()->runForMultiple(
            $iterable ?: Tenant::initialized()->lazyById(50), // @phpstan-ignore-line
            function (Tenant $tenant) use ($callback) {
                try {
                    $callback($tenant);
                } catch (Throwable $e) {
                    captureException($e);
                }
            },
        );
    }
}

if (!function_exists('tenant_or_fail')) {
    /**
     * Get tenant instance or fail.
     */
    function tenant_or_fail(): Tenant
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        return $tenant;
    }
}

if (!function_exists('build_site')) {
    /**
     * @param  array<string, mixed>|null  $data
     */
    function build_site(string $event, ?array $data = null): ReleaseEventsBuilder
    {
        $builder = new ReleaseEventsBuilder();

        $builder->handle($event, $data);

        return $builder;
    }
}

if (!function_exists('is_not_empty_string')) {
    /**
     * Check is the given variable is not an empty string.
     *
     * @return ($content is non-empty-string ? true : false)
     */
    function is_not_empty_string(mixed $content): bool
    {
        return is_string($content) && !empty($content);
    }
}

if (!function_exists('ingest')) {
    /**
     * @param  array<string, mixed>  $data
     */
    function ingest(array $data, ?string $tenantId = null, string $type = 'event'): int
    {
        $payload = [
            'type' => $type,
            'data' => $data,
            'tenant_id' => $tenantId ?: tenant()?->id, // @phpstan-ignore-line
            'environment' => app()->environment(),
            '_time' => now()->toRfc3339String(true),
        ];

        $pushed = RedisFacade::connection('default')->command('lPush', ['ingest', json_encode($payload)]);

        if (!is_int($pushed)) {
            return 0;
        }

        return $pushed;
    }
}

if (!function_exists('assets_url')) {
    /**
     * Get assets url for specific path.
     */
    function assets_url(string $path): string
    {
        return sprintf(
            'https://assets.stori.press/%s',
            Str::after($path, 'assets/'),
        );
    }
}

if (!function_exists('app_url')) {
    /**
     * Get app url.
     *
     * @param  array<string, mixed>  $queries
     */
    function app_url(string $path, array $queries = []): string
    {
        $domain = match (app()->environment()) {
            'local' => 'localhost:3333',
            'testing' => 'localhost:8000',
            'development' => 'storipress.dev',
            'staging' => 'storipress.pro',
            default => 'stori.press',
        };

        $url = sprintf('https://%s/%s', $domain, ltrim($path, '/'));

        if (empty($queries)) {
            return $url;
        }

        return sprintf('%s?%s', $url, http_build_query($queries));
    }
}

if (!function_exists('script_url')) {
    /**
     * Get script provider url.
     *
     * @param  'webflow'|'wordpress'  $platform
     */
    function script_url(string $platform, string $client): string
    {
        $endpoint = app()->isProduction()
            ? 'script-providers'
            : 'script-providers-staging';

        $query = http_build_query([
            'client' => $client,
            'platform' => $platform,
        ]);

        return sprintf('https://%s.storipress.workers.dev/storipress.mjs?%s', $endpoint, $query);
    }
}

if (!function_exists('script_tag')) {
    /**
     * Get script provider url.
     *
     * @param  'webflow'|'wordpress'  $platform
     */
    function script_tag(string $platform, ?string $tenant = null): string
    {
        $url = script_url($platform, $tenant ?: tenant_or_fail()->id);

        return sprintf('<script async type="module" src="%s"></script>', $url);
    }
}

if (!function_exists('temp_file')) {
    /**
     * Create a temp file and get path.
     */
    function temp_file(): string
    {
        $origin = tempnam(sys_get_temp_dir(), 'storipress-');

        if ($origin === false) {
            throw new RuntimeException('Something went wrong when creating temp file.');
        }

        $path = mb_strtolower($origin);

        if (rename($origin, $path) === false) {
            throw new RuntimeException('Something went wrong when creating temp file.');
        }

        register_shutdown_function(
            fn () => is_file($path) && @unlink($path),
        );

        return $path;
    }
}

if (!function_exists('unique_token')) {
    /**
     * Generate unique uuid4 token.
     */
    function unique_token(): string
    {
        return (string) Str::uuid();
    }
}

if (!function_exists('hmac')) {
    /**
     * Hmac data.
     *
     * @param  array<string>  $data
     */
    function hmac(array $data, bool $sort = true, string $algo = 'sha256'): string
    {
        if ($sort) {
            ksort($data);
        }

        /** @var string $key */
        $key = config('app.key');

        return hash_hmac($algo, implode('|', $data), $key);
    }
}

if (!function_exists('base62_crc32')) {
    /**
     * CRC32 with base62 encoding and padding.
     */
    function base62_crc32(string $data, int $length = 0, string $pad = ''): string
    {
        $crc32 = crc32($data);

        $base62 = Base62Proxy::encodeInteger($crc32);

        return Str::padLeft($base62, $length, $pad);
    }
}

if (!function_exists('roles')) {
    /**
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     title: string,
     *     level: int,
     * }>
     */
    function roles(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'owner',
                'title' => 'Site Owner',
                'level' => 10,
            ],
            [
                'id' => 2,
                'name' => 'admin',
                'title' => 'Administrator',
                'level' => 8,
            ],
            [
                'id' => 3,
                'name' => 'editor',
                'title' => 'Editor',
                'level' => 6,
            ],
            [
                'id' => 4,
                'name' => 'author',
                'title' => 'Author',
                'level' => 4,
            ],
            [
                'id' => 5,
                'name' => 'contributor',
                'title' => 'Contributor',
                'level' => 2,
            ],
        ];
    }
}

if (!function_exists('find_role')) {
    function find_role(int|string $id): stdClass
    {
        $roles = roles();

        $role = Arr::first($roles, function (array $role) use ($id) {
            return is_numeric($id)
                ? $role['id'] === (int) $id
                : $role['name'] === $id;
        });

        Assert::isArray($role);

        return json_decode(json_encode($role)); // @phpstan-ignore-line
    }
}

if (!function_exists('to_elr')) {
    /**
     * Generate elr link.
     */
    function to_elr(string $id, string $url): string
    {
        $link = base64_encode($url);

        $salt = Str::random(4);

        $signature = hmac(
            ['id' => $id, 'link' => $link, 'salt' => $salt],
            true,
            'md5',
        );

        return route('elr', [
            'id' => $id,
            'link' => rawurlencode($link),
            'salt' => $salt,
            'signature' => $signature,
        ]);
    }
}
