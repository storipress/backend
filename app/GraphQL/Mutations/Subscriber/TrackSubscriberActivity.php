<?php

namespace App\GraphQL\Mutations\Subscriber;

use App\Console\Commands\Subscriber\GatherDailyMetrics;
use App\Console\Schedules\Daily\GatherProphetMetrics;
use App\Events\Entity\Subscriber\SubscriberActivityRecorded;
use App\Exceptions\BadRequestHttpException;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Page;
use App\Models\Tenants\Subscriber;
use App\Models\Tenants\SubscriberEvent;
use App\Models\Tenants\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use stdClass;

class TrackSubscriberActivity
{
    /**
     * @var array<int, non-empty-string>
     */
    public array $events = [
        // v1 paywall
        'article.seen',
        'article.shared',
        'article.link.clicked',
        'page.seen',
        'desk.seen',
        'author.seen',
        'home.seen',

        // leaky paywall
        'article.hyperlink.clicked', // 點擊文章超連結時觸發
        'article.text.selected', // 文章頁面選取文字時觸發
        'article.text.copied', // 文章頁面複製文字時觸發
        // 'article.media.clicked', // 點擊文章多媒體時觸發
        'article.viewed', // 訪問文章頁面或向下滑動載入新文章時觸發
        'article.read', // 閱讀文章進度變動時觸發
        'page.viewed', // 訪問任何頁面時觸發
        'paywall.reached', // 每篇文章到達 paywall 邊界觸發
        'paywall.activated', // 到達每週數量上限觸發
        'paywall.canceled', // 使用者關閉 dialog（往上滑）
        'subscriber.signed_in', // 讀入登入/註冊
    ];

    /**
     * @param  array{
     *     anonymous_id?: string,
     *     name: string,
     *     target_id?: string|null,
     *     data: stdClass|null,
     * }  $args
     */
    public function __invoke($_, array $args): bool
    {
        $tenant = tenant();

        if (! ($tenant instanceof Tenant)) {
            return false;
        }

        if (! in_array($args['name'], $this->events, true)) {
            return false;
        }

        $subscriber = Subscriber::find(auth()->id());

        $signedIn = $subscriber instanceof Subscriber;

        if (! $signedIn && empty($args['anonymous_id'])) {
            return false;
        }

        $type = $this->nameToTarget($args['name']);

        if ($type === null) {
            $args['target_id'] = null;
        } elseif ($type === Subscriber::class) {
            if (! $signedIn) {
                return false;
            }

            $args['target_id'] = (string) $subscriber->id;
        } else {
            if (empty($args['target_id'])) {
                throw new BadRequestHttpException();
            }

            // ensure target exists
            if (! $type::where('id', $args['target_id'])->exists()) {
                throw new BadRequestHttpException();
            }
        }

        if ($args['data'] && isset($args['data']->timestamp) && is_int($args['data']->timestamp)) {
            $occurredAt = Carbon::createFromTimestampMs($args['data']->timestamp);
        }

        $event = SubscriberEvent::create([
            'anonymous_id' => $args['anonymous_id'] ?? Str::uuid(),
            'subscriber_id' => $subscriber?->id ?: 0,
            'target_id' => $args['target_id'],
            'target_type' => $type,
            'name' => $args['name'],
            'data' => $args['data'],
            'occurred_at' => $occurredAt ?? now(),
        ]);

        if ($event->subscriber_id > 0) {
            SubscriberEvent::where('subscriber_id', '=', 0)
                ->where('anonymous_id', '=', $event->anonymous_id)
                ->update(['subscriber_id' => $event->subscriber_id]);
        }

        if ($signedIn) {
            SubscriberActivityRecorded::dispatch(
                $tenant->id,
                $subscriber->id,
                $args['name'],
            );

            Artisan::queue(GatherDailyMetrics::class, [
                '--date' => now()->toDateString(),
                '--tenants' => [$tenant->id],
            ]);
        }

        if ($tenant->has_prophet) {
            Artisan::queue(GatherProphetMetrics::class, [
                '--date' => now()->toDateString(),
                '--tenants' => [$tenant->id],
            ]);
        }

        return true;
    }

    /**
     * Convert event name to target class.
     */
    public function nameToTarget(string $name): ?string
    {
        if (in_array($name, ['page.viewed'], true)) {
            return null;
        }

        return match (Str::before($name, '.')) {
            'article' => Article::class,
            'page' => Page::class,
            'desk' => Desk::class,
            'author' => User::class,
            'subscriber' => Subscriber::class,
            default => null,
        };
    }
}
