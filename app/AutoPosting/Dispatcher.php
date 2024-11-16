<?php

namespace App\AutoPosting;

use App\AutoPosting\Layers\ArticleCheckerLayer;
use App\AutoPosting\Layers\ContentCheckerLayer;
use App\AutoPosting\Layers\ContentConverterLayer;
use App\AutoPosting\Layers\PartnerContentSyncingLayer;
use App\AutoPosting\Layers\PartnerResponseProcessingLayer;
use App\AutoPosting\Layers\PlatformCheckerLayer;
use App\AutoPosting\Layers\PostProcessingLayer;
use App\AutoPosting\Layers\PreProcessingLayer;
use App\Exceptions\ErrorException;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use Webmozart\Assert\Assert;

use function Sentry\captureException;

class Dispatcher
{
    /**
     * The order is important.
     *
     * @var array<string, string|null>
     */
    protected array $platforms = [
        'facebook' => null, // TODO
        'twitter' => null, // TODO
        'linkedin' => 'LinkedIn',
        'slack' => null, // TODO
    ];

    /**
     * Layers that will be run for auto-posting. The order is important.
     *
     * @var class-string[]
     */
    protected array $layers = [
        PreProcessingLayer::class,
        PlatformCheckerLayer::class,
        ArticleCheckerLayer::class,
        ContentCheckerLayer::class,
        ContentConverterLayer::class,
        PartnerContentSyncingLayer::class,
        PartnerResponseProcessingLayer::class,
        PostProcessingLayer::class,
    ];

    protected bool $executed = false;

    /**
     * @param  'create'|'update'|'unpublish'|'trash'|'none'  $action
     * @param  array<mixed>  $extra  the extra data for all layers
     */
    public function __construct(
        public Tenant $tenant,
        public Article $article,
        public string $action,
        public array $extra,
    ) {}

    /**
     * Run auto-posting for all supported platforms.
     */
    public function handle(): void
    {
        if ($this->executed) {
            throw new RuntimeException('Execute the handle method multiple times.');
        }

        $this->executed = true;

        $platforms = array_filter($this->platforms);

        foreach ($platforms as $platform => $name) {
            $result = [];
            $extra = $this->extra[$platform] ?? [];

            foreach ($this->layers as $layer) {
                $job = sprintf(
                    'App\\AutoPosting\\%s\\%s',
                    $name,
                    Str::afterLast($layer, '\\'),
                );

                try {
                    $instance = app()->make($job);

                    Assert::isInstanceOf($instance, $layer);
                } catch (Throwable $e) {
                    captureException($e);

                    continue 2;
                }

                try {
                    $result = $instance->handle($this, is_array($result) ? $result : [], $extra);
                } catch (ErrorException $e) {
                    $instance->logStopped($e, $layer);

                    $instance->reportStopped($e);

                    continue 2;
                } catch (Throwable $e) {
                    $instance->logFailed($e, $layer);

                    $instance->reportFailed($e);

                    continue 2;
                }

                // does not need to notify and log
                if ($result === false) {
                    continue 2;
                }
            }
        }
    }

    /**
     * @param  string[]  $targets
     */
    public function only(array $targets): void
    {
        $this->platforms = Arr::only($this->platforms, $targets);
    }
}
