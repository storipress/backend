<?php

namespace App\GraphQL\Mutations\Site;

use App\Builder\ReleaseEventsBuilder;
use App\Exceptions\InternalServerErrorHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;
use Webmozart\Assert\Assert;

use function Sentry\captureException;

final class DisableCustomDomain extends Mutation
{
    public function __invoke(): Tenant
    {
        $this->authorize('write', Tenant::class);

        /** @var Tenant $tenant */
        $tenant = tenant();

        $origin = $tenant->custom_domain;

        if (empty($origin)) {
            return $tenant;
        }

        if (!$tenant->update(['custom_domain' => null])) {
            throw new InternalServerErrorHttpException();
        }

        /** @var \Redis $redis */
        $redis = Redis::connection('cdn')->client();

        $redis->del(
            $origin,
            sprintf('www.%s', $origin),
            Str::remove('www.', $origin),
        );

        $message = json_encode([
            'event' => 'terminate',
            'tenant' => $tenant->getKey(),
        ]);

        Assert::stringNotEmpty($message);

        $channel = sprintf('cdn_caddy_%s', app()->environment());

        $redis->publish($channel, $message);

        if (!empty($tenant->postmark)) {
            /** @var int $postmarkId */
            $postmarkId = $tenant->postmark['id'];

            try {
                app('postmark.account')->deleteDomain($postmarkId);
            } catch (Throwable $e) {
                captureException($e);
            }

            $tenant->update(['postmark' => null]);
        }

        Http::connectTimeout(5)
            ->timeout(10)
            ->withUserAgent('storipress/2022-09-01')
            ->post('https://api.cloudflare.com/client/v4/pages/webhooks/deploy_hooks/cf0506cc-0b75-4be3-9376-2c22ac608514');

        $builder = new ReleaseEventsBuilder();

        $builder->handle('domain:disable', ['domain' => $origin]);

        UserActivity::log(
            name: 'publication.custom_domain.disable',
        );

        return $tenant;
    }
}
