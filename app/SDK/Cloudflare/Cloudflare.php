<?php

namespace App\SDK\Cloudflare;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Sentry\State\Scope;
use Webmozart\Assert\Assert;

use function Sentry\configureScope;

/**
 * @phpstan-type CloudflarePageDeploymentStage array{
 *     name: 'queued'|'initialize'|'clone_repo'|'build'|'deploy',
 *     started_on: string|null,
 *     ended_on: string|null,
 *     status: 'success'|'idle'|'active'|'failure'|'canceled',
 * }
 * @phpstan-type CloudflarePageDeployment array{
 *     id: string,
 *     short_id: string,
 *     project_id: string,
 *     project_name: string,
 *     environment: 'preview'|'production',
 *     production_branch: string,
 *     url: string,
 *     created_on: string,
 *     modified_on: string,
 *     latest_stage: CloudflarePageDeploymentStage,
 *     deployment_trigger: array{
 *         type: 'push'|'ad_hoc',
 *         metadata: array{
 *             branch: string,
 *             commit_hash: string,
 *             commit_message: string,
 *             commit_dirty: bool,
 *         },
 *     },
 *     stages: CloudflarePageDeploymentStage[],
 *     build_config: array{
 *         build_command: string|null,
 *         destination_dir: string|null,
 *         root_dir: string|null,
 *         web_analytics_tag: string|null,
 *         web_analytics_token: string|null,
 *     },
 *     env_vars: array<string, array{
 *         type: string,
 *         value: string,
 *     }>,
 *     kv_namespaces: array<string, array<string, string>>,
 *     compatibility_date: string,
 *     compatibility_flags: string[],
 *     build_image_major_version: int,
 *     usage_model: 'unbound'|'bundled',
 *     aliases: string[],
 *     is_skipped: bool,
 * }
 */
class Cloudflare
{
    public const BASE_URL = 'https://api.cloudflare.com/client/v4';

    public PendingRequest $http;

    /**
     * Constructor.
     */
    public function __construct(string $key, string $account)
    {
        $this->http = app('http2')
            ->baseUrl(
                Str::of(static::BASE_URL)
                    ->append('/accounts/')
                    ->append($account)
                    ->toString(),
            )
            ->withToken($key);
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     subdomain: string,
     *     domains: string[],
     *     production_branch: string,
     *     production_script_name: string,
     *     preview_script_name: string,
     *     created_on: string,
     *     source: array<string, mixed>,
     *     latest_deployment: array<string, mixed>|null,
     *     canonical_deployment: array<string, mixed>|null,
     *     build_config: array<string, string|null>,
     *     deployment_configs: array<'preview'|'production', array{
     *         always_use_latest_compatibility_date: bool,
     *         compatibility_date: string,
     *         compatibility_flags: string[],
     *         build_image_major_version: int,
     *         usage_model: 'unbound'|'bundled',
     *         fail_open: bool,
     *         placement: array{
     *             mode: 'smart',
     *         },
     *         env_vars: array<string, array{
     *             type: string,
     *             value: string,
     *         }>,
     *         kv_namespaces: array<string, array<string, string>>,
     *     }>,
     * }
     *
     * @throws RequestException
     */
    public function createPage(string $name): array
    {
        $storage = config('services.cloudflare.kv.customer_site_cache');

        Assert::stringNotEmpty($storage);

        $config = [
            'compatibility_date' => '2024-02-29',
            'compatibility_flags' => ['nodejs_compat'],
            'placement' => ['mode' => 'smart'],
            'kv_namespaces' => [
                'STORAGE' => ['namespace_id' => $storage],
            ],
        ];

        // @phpstan-ignore-next-line
        return $this->http
            ->post('/pages/projects', [
                'name' => $name,
                'production_branch' => 'main',
                'deployment_configs' => [
                    'preview' => $config,
                    'production' => $config,
                ],
            ])
            ->throw([$this, 'throw'])
            ->json('result');
    }

    /**
     * @return CloudflarePageDeployment[]
     *
     * @throws RequestException
     */
    public function getPageDeployments(string $name, int $page = 1): array
    {
        $endpoint = sprintf('/pages/projects/%s/deployments', $name);

        // @phpstan-ignore-next-line
        return $this->http
            ->get($endpoint, [
                'page' => $page,
                'per_page' => 25, // the maximum
            ])
            ->throw([$this, 'throw'])
            ->json('result');
    }

    /**
     * @throws RequestException
     */
    public function deletePageDeployment(string $name, string $deployment, bool $force = false): bool
    {
        $endpoint = sprintf(
            '/pages/projects/%s/deployments/%s?force=%s',
            $name,
            $deployment,
            $force ? 'true' : 'false',
        );

        return $this->http
            ->delete($endpoint)
            ->throw([$this, 'throw'])
            ->ok();
    }

    /**
     * @return array<int, array{
     *     name: string,
     *     expiration?: int,
     *     metadata?: array<array-key, mixed>,
     * }>
     *
     * @throws RequestException
     */
    public function getKVKeys(string $namespace, string $prefix = ''): array
    {
        $endpoint = sprintf('/storage/kv/namespaces/%s/keys', $namespace);

        // @phpstan-ignore-next-line
        return $this->http
            ->get($endpoint, [
                'limit' => 1_000, // the maximum
                'prefix' => $prefix,
            ])
            ->throw([$this, 'throw'])
            ->json('result');
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  positive-int|null  $ttl
     *
     * @throws RequestException
     */
    public function setKVKey(
        string $namespace,
        string $key,
        string $value,
        array $metadata = [],
        ?int $ttl = null,
    ): bool {
        return $this->setKVKeys($namespace, [
            compact(
                'key',
                'value',
                'metadata',
                'ttl',
            ),
        ]);
    }

    /**
     * @param  array<int, array{
     *     key: string,
     *     value: string,
     *     metadata?: array<array-key, mixed>,
     *     ttl?: positive-int|null,
     * }>  $data
     *
     * @throws RequestException
     */
    public function setKVKeys(
        string $namespace,
        array $data,
    ): bool {
        $endpoint = sprintf('/storage/kv/namespaces/%s/bulk', $namespace);

        $payload = array_map(function (array $datum) {
            return [
                'base64' => true,
                'expiration_ttl' => isset($datum['ttl']) ? max($datum['ttl'], 60) : null,
                'key' => $datum['key'],
                'metadata' => empty($datum['metadata']) ? null : json_encode($datum['metadata']),
                'value' => base64_encode($datum['value']),
            ];
        }, $data);

        return $this->http
            ->put($endpoint, $payload)
            ->throw([$this, 'throw'])
            ->ok();
    }

    /**
     * @throws RequestException
     */
    public function deleteKVKey(string $namespace, string $key): bool
    {
        return $this->deleteKVKeys($namespace, [$key]);
    }

    /**
     * @param  array<int, string>  $keys
     *
     * @throws RequestException
     */
    public function deleteKVKeys(string $namespace, array $keys): bool
    {
        if (empty($keys)) {
            return true;
        }

        $endpoint = sprintf('/storage/kv/namespaces/%s/bulk', $namespace);

        return $this->http
            ->delete($endpoint, $keys)
            ->throw([$this, 'throw'])
            ->ok();
    }

    public function throw(Response $response, RequestException $exception): void
    {
        configureScope(function (Scope $scope) use ($response): void {
            $error = $response->json('errors.0');

            if (!is_array($error)) {
                return;
            }

            $scope->setContext('error', $error);
        });
    }
}
