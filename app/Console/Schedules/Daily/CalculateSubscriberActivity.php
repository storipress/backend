<?php

namespace App\Console\Schedules\Daily;

use App\Console\Schedules\Command;
use App\Jobs\Typesense\MakeSearchable;
use App\Models\Tenant;
use App\Models\Tenants\Subscriber;
use App\Models\Tenants\SubscriberEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\LazyCollection;
use Stripe\Exception\InvalidRequestException;
use Throwable;

use function Sentry\captureException;

class CalculateSubscriberActivity extends Command
{
    /**
     * Execute the console command.
     *
     * @see https://www.notion.so/storipress/5bce25e462ac4ce8872a2713e0d145a5
     */
    public function handle(): int
    {
        $now = now()->startOfDay()->toImmutable();

        $from = [
            7 => $now->subDays(7),
            30 => $now->subDays(30),
            90 => $now->subDays(90),
        ];

        runForTenants(function (Tenant $tenant) use ($from, $now) {
            Subscriber::disableSearchSyncing();

            /** @var LazyCollection<int, Subscriber> $subscribers */
            $subscribers = Subscriber::withoutEagerLoads()
                ->with(['events' => function (HasMany $query) {
                    $query->select(['subscriber_id', 'target_id', 'name', 'occurred_at']);
                }])
                ->lazyById(50);

            foreach ($subscribers as $subscriber) {
                $total = $subscriber->events;

                $past90 = $total->where('occurred_at', '>=', $from[90]);

                $past30 = $past90->where('occurred_at', '>=', $from[30]);

                $past7 = $past30->where('occurred_at', '>=', $from[7]);

                $subscriber->update([
                    'revenue' => $this->revenue($tenant, $subscriber),
                    'activity' => $this->activity($past90),
                    'active_days_last_30' => $past30->pluck('occurred_at')->map->toDateString()->unique()->count(),
                    'shares_total' => $total->where('name', '=', 'article.shared')->count(),
                    'shares_last_7' => $past7->where('name', '=', 'article.shared')->count(),
                    'shares_last_30' => $past30->where('name', '=', 'article.shared')->count(),
                    'email_receives' => $total->where('name', '=', 'email.received')->count(),
                    'email_opens_total' => $total->where('name', '=', 'email.opened')->count(),
                    'email_opens_last_7' => $past7->where('name', '=', 'email.opened')->count(),
                    'email_opens_last_30' => $past30->where('name', '=', 'email.opened')->count(),
                    'unique_email_opens_total' => $total->where('name', '=', 'email.opened')->unique('target_id')->count(),
                    'unique_email_opens_last_7' => $past7->where('name', '=', 'email.opened')->unique('target_id')->count(),
                    'unique_email_opens_last_30' => $past30->where('name', '=', 'email.opened')->unique('target_id')->count(),
                    'email_link_clicks_total' => $total->where('name', '=', 'email.link_clicked')->count(),
                    'email_link_clicks_last_7' => $past7->where('name', '=', 'email.link_clicked')->count(),
                    'email_link_clicks_last_30' => $past30->where('name', '=', 'email.link_clicked')->count(),
                    'unique_email_link_clicks_total' => 0, // @todo
                    'unique_email_link_clicks_last_7' => 0, // @todo
                    'unique_email_link_clicks_last_30' => 0, // @todo
                    'article_views_total' => $total->where('name', '=', 'article.seen')->count(),
                    'article_views_last_7' => $past7->where('name', '=', 'article.seen')->count(),
                    'article_views_last_30' => $past30->where('name', '=', 'article.seen')->count(),
                    'unique_article_views_total' => $total->where('name', '=', 'article.seen')->unique('target_id')->count(),
                    'unique_article_views_last_7' => $past7->where('name', '=', 'article.seen')->unique('target_id')->count(),
                    'unique_article_views_last_30' => $past30->where('name', '=', 'article.seen')->unique('target_id')->count(),
                ]);
            }

            Subscriber::enableSearchSyncing();

            Subscriber::withoutEagerLoads()
                ->where('id', '>', 0)
                ->where('updated_at', '>=', $now)
                ->select(['id'])
                ->chunkById(50, function (Collection $subscribers) {
                    MakeSearchable::dispatchSync($subscribers);
                });
        });

        return static::SUCCESS;
    }

    /**
     * @param  Collection<int, SubscriberEvent>  $past90
     */
    protected function activity(Collection $past90): float
    {
        $received = $past90
            ->where('name', '=', 'email.received')
            ->count();

        $opened = $past90
            ->where('name', '=', 'email.opened')
            ->count();

        $seen = $past90
            ->where('name', '=', 'article.seen')
            ->groupBy(
                fn (SubscriberEvent $event) => $event->occurred_at->format('W'),
            )
            ->count();

        return ($opened + $seen) / max($received, 1) * 100;
    }

    protected function revenue(Tenant $tenant, Subscriber $subscriber): int
    {
        $ret = 0;

        if (empty($tenant->stripe_account_id)) {
            return $ret;
        }

        if (!$subscriber->hasStripeId()) {
            return $ret;
        }

        if ($subscriber->subscribed('manual')) {
            return $ret;
        }

        $stripe = $subscriber->stripe();

        if ($stripe === null) {
            return $ret;
        }

        try {
            $invoices = $stripe->invoices->all([
                'customer' => $subscriber->stripe_id,
                'status' => 'paid',
                'limit' => 100,
            ]);

            foreach ($invoices->autoPagingIterator() as $invoice) {
                $ret += $invoice->total;
            }

            return $ret;
        } catch (InvalidRequestException $e) {
            // No such customer
            if ($e->getStripeCode() !== 'resource_missing') {
                captureException($e);
            }

            return 0;
        } catch (Throwable $e) {
            captureException($e);

            return 0;
        }
    }
}
