<?php

namespace App\Builder;

use App\Console\Commands\Cloudflare\Pages\ClearSiteCacheByTenant;
use App\Enums\AccessToken\Type;
use App\Models\AccessToken;
use App\Models\CloudflarePage;
use App\Models\Tenant;
use App\Models\Tenants\Release;
use App\Models\Tenants\ReleaseEvent;
use App\Notifications\Site\SiteDeploymentStartedNotification;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Segment\Segment;
use Sentry\State\Scope;
use Throwable;
use Webmozart\Assert\Assert;

use function Sentry\captureException;
use function Sentry\withScope;

class ReleaseEventsBuilder
{
    protected string $event;

    /**
     * @var string[]
     */
    protected array $data;

    /**
     * @param  bool  $dryRun  If true, the aws lambda will not be called
     */
    public function __construct(protected bool $dryRun = false)
    {
        if ($this->getEnv() === null) {
            $this->dryRun = true;
        }
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public function handle(string $event, ?array $data = null): ?Release
    {
        if ($event === 'article:publish' && tenant('id') === 'PEFDXPPDN') {
            return null; // skip abusing
        }

        $releaseEvent = $this->save($event, $data);

        if ($releaseEvent === null) {
            return null;
        }

        if (!ReleaseEvent::isEager($event)) {
            return null;
        }

        return $this->run();
    }

    /**
     * @param  array<string, mixed>|null  $data
     *
     * @link https://www.notion.so/storipress/1b20666b0e9047c8961142c742e3ce93?v=9453abd2eb2d433fb837ef67a3ec1229
     */
    public function save(string $event, ?array $data = null): ?ReleaseEvent
    {
        // invalid name
        if (empty($event)) {
            return null;
        }

        if (empty($data)) {
            $data = null;
        }

        $event = Str::lower($event);

        $checksum = $data === null ? null : hmac($data, true, 'md5'); // @phpstan-ignore-line

        if ($this->hasSamePendingEventData($event, $checksum)) {
            return null;
        }

        return ReleaseEvent::create([
            'name' => $event,
            'data' => $data,
            'checksum' => $checksum,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function run(): ?Release
    {
        $start = microtime(true);

        try {
            /** @var Release|null $release */
            $release = DB::transaction(function () {
                // utilize a subquery in order to prevent performing a full table scan.
                $events = ReleaseEvent::lockForUpdate()
                    ->whereIn('id', function (Builder $query) {
                        $query->select('id')
                            ->from((new ReleaseEvent())->getTable())
                            ->whereNull('release_id');
                    })
                    ->get();

                if ($events->isEmpty()) {
                    return null;
                }

                $release = $this->invokeGenerator();

                if ($release === null) {
                    return null;
                }

                ReleaseEvent::whereIn('id', $events->modelKeys())->update([
                    'release_id' => $release->id,
                ]);

                return $release;
            });
        } catch (Throwable $e) {
            withScope(function (Scope $scope) use ($e, $start): void {
                $scope->setContext('debug', [
                    'time' => microtime(true) - $start,
                    'connections' => count(DB::getConnections()),
                ]);

                captureException($e);
            });

            return null;
        }

        if (
            $release &&
            app()->isProduction() &&
            (($tenant = tenant()) instanceof Tenant) &&
            !Str::contains($tenant->name, ['playwright', 'e2e', 'test publication'], true)
        ) {
            Artisan::queue(ClearSiteCacheByTenant::class, [
                'tenant' => $tenant->id,
            ]);
        }

        return $release;
    }

    /**
     * Invoke generator to build new site content.
     */
    protected function invokeGenerator(): ?Release
    {
        $env = $this->getEnv();

        /** @var Tenant $tenant */
        $tenant = tenant();

        Assert::isInstanceOf($tenant->cloudflare_page, CloudflarePage::class);

        /** @var Release $release */
        $release = Release::create()->fresh();

        if ($this->dryRun) {
            return $release;
        }

        if ($env === 'development' && Str::contains($tenant->name, ['playwright', 'e2e', 'test publication'], true)) {
            return $release;
        }

        $token = $tenant->accessToken ?: $tenant->accessToken()->create([
            'name' => 'newstand-api',
            'token' => AccessToken::token(Type::tenant()),
            'abilities' => '*',
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'expires_at' => now()->addYears(5),
        ]);

        Assert::isInstanceOf($token, AccessToken::class);

        if ($tenant->custom_site_template && $tenant->custom_site_template_path) {
            $function = 'generator-next';

            $payload = [
                'token' => $token->token,
                'client_id' => $tenant->id,
                'release_id' => (string) $release->id,
                'template_url' => $this->createPresignedTemplateUrl($tenant->custom_site_template_path),
                'upload_url' => $this->createPresignedDeployUrl(
                    $token->token,
                    $tenant->id,
                    $tenant->cloudflare_page->name,
                    (string) $release->id,
                ),
            ];
        } else {
            $function = $this->getGeneratorFunctionName($tenant, $env);

            $payload = [
                'environment' => $env,
                'page_id' => $tenant->cloudflare_page->name,
                'token' => $token->token,
                'client_id' => $tenant->id,
                'release_id' => (string) $release->id,
                'upload_url' => $this->createPresignedDeployUrl(
                    $token->token,
                    $tenant->id,
                    $tenant->cloudflare_page->name,
                    (string) $release->id,
                ),
            ];
        }

        app('aws')->createLambda()->invoke([
            'FunctionName' => $function,
            'InvocationType' => 'Event',
            'Payload' => json_encode($payload),
        ]);

        Segment::track([
            'userId' => (string) $tenant->owner->id,
            'event' => 'tenant_build_in_queued',
            'properties' => [
                'tenant_uid' => $tenant->id,
                'tenant_name' => $tenant->name,
                'tenant_build_id' => $release->id,
                'tenant_build_meta' => $release->meta ?: [],
            ],
            'context' => [
                'groupId' => $tenant->id,
            ],
        ]);

        $tenant->owner->notify(
            new SiteDeploymentStartedNotification(
                $tenant->id,
                $release->id,
            ),
        );

        return $release;
    }

    protected function getGeneratorFunctionName(Tenant $tenant, ?string $env): string
    {
        $prefix = [
            'v1' => 'generator',
            'v2' => 'generator-v2',
        ];

        $postfix = [
            'production' => 'production',
            'staging' => 'staging',
            'development' => 'dev',
        ];

        return sprintf(
            '%s-%s',
            $prefix[$tenant->generator] ?? $prefix['v2'],
            $postfix[$env] ?? $postfix['development'],
        );
    }

    protected function getEnv(): ?string
    {
        $env = app()->environment();

        if (!in_array($env, ['production', 'staging', 'development'], true)) {
            return null;
        }

        return $env;
    }

    /**
     * Check if there has the same pending event data.
     */
    protected function hasSamePendingEventData(string $event, ?string $checksum): bool
    {
        return ReleaseEvent::where('name', $event)
            ->where('checksum', $checksum)
            ->whereNull('release_id')
            ->exists();
    }

    protected function createPresignedTemplateUrl(string $path): string
    {
        $expireOn = now()->addHour();

        return Storage::cloud()->temporaryUrl($path, $expireOn);
    }

    protected function createPresignedDeployUrl(string $token, string $clientId, string $pageId, string $releaseId): string
    {
        $s3 = app('aws')->createS3();

        $expireOn = now()->addHour();

        $path = sprintf(
            'site-deployments/%s-%s-%d-%s',
            $clientId,
            $releaseId,
            time(),
            unique_token(),
        );

        $command = $s3->getCommand('putObject', [
            'Bucket' => 'storipress',
            'Key' => $path,
            'Metadata' => [
                'sp-deploy' => json_encode([
                    'token' => $token,
                    'page_id' => $pageId,
                    'client_id' => $clientId,
                    'release_id' => $releaseId,
                    'source' => 'generator-next',
                ]),
            ],
        ]);

        $request = $s3->createPresignedRequest($command, $expireOn->getTimestamp());

        return (string) $request->getUri();
    }
}
