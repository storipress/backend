<?php

namespace App\Console\Commands\Report;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use JoliCode\Slack\Api\Model\ChatPostMessagePostResponse200;

/**
 * @template TSentryCrashFreeData of array{
 *   abnormal: int,
 *   crashed: int,
 *   healthy: int,
 *   errored: int,
 * }
 *
 * @link https://docs.sentry.io/api/releases/retrieve-release-health-session-statistics/
 */
class ReportWeeklyCrashFree extends ReportAnalyticMetrics
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:weekly-crash-free';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Report weekly crash free';

    /**
     * Report number decimal number.
     */
    protected int $decimal = 3;

    /**
     * Sentry API client.
     */
    protected PendingRequest $sentry;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $apiToken = config('services.sentry.token');

        if (empty($apiToken) || !is_string($apiToken)) {
            $this->error('Slack API token is not set yet.');

            return self::FAILURE;
        }

        // the priority is important
        $envs = ['production', 'dev'];

        $this->sentry = Http::baseUrl('https://sentry.io/api/0/organizations/storipress/sessions/')
            ->connectTimeout(5)
            ->timeout(10)
            ->retry(3, 1000)
            ->withToken($apiToken)
            ->withUserAgent('storipress/2022-08-18');

        $content = file_get_contents(
            resource_path('notifications/slack/weekly-crash-free-report.json'),
        );

        if (empty($content)) {
            $this->error('Fail to load the report template.');

            return self::FAILURE;
        }

        $ts = null;

        foreach ($envs as $env) {
            $this->sentry->withOptions([
                'query' => [
                    'project' => 6376127,
                    'groupBy' => 'session.status',
                    'interval' => '1d',
                    'environment' => $env,
                ],
            ]);

            $session = $this->data('sum(session)');

            $user = $this->data('count_unique(user)');

            $mapping = [
                '{env}' => ucfirst($env),
                '{date}' => now()->subRealDay()->toFormattedDateString(),
                '{session_percentage}' => $session['percentage'],
                '{session_diff}' => $session['diff'] >= 0 ? '+' . $session['diff'] : $session['diff'],
                '{session_diff_emoji}' => $session['diff'] >= 0 ? '💹' : '‼️',
                '{user_percentage}' => $user['percentage'],
                '{user_diff}' => $user['diff'] >= 0 ? '+' . $user['diff'] : $user['diff'],
                '{user_diff_emoji}' => $user['diff'] >= 0 ? '💹' : '‼️',
            ];

            /** @var ChatPostMessagePostResponse200 $response */
            $response = $this->sendToSlack(strtr($content, $mapping), $ts ? ['thread_ts' => $ts] : []);

            $ts = $ts ?: $response->getTs();
        }

        return self::SUCCESS;
    }

    /**
     * @return array{
     *   percentage: float,
     *   diff: float,
     * }
     */
    protected function data(string $field): array
    {
        $latest = $this->transform(
            $this->fetch([
                'field' => $field,
                'statsPeriod' => '7d',
            ]),
        );

        $compareTo = $this->transform(
            $this->fetch([
                'field' => $field,
                'statsPeriodStart' => '14d',
                'statsPeriodEnd' => '7d',
            ]),
        );

        return [
            'percentage' => $latest,
            'diff' => round($latest - $compareTo, $this->decimal),
        ];
    }

    /**
     * @param  array<string, string>  $queries
     * @return TSentryCrashFreeData
     */
    protected function fetch(array $queries): array
    {
        $response = $this->sentry->get('', $queries);

        /** @var array<int, mixed> $groups */
        $groups = $response->json('groups');

        $collection = collect($groups)->mapWithKeys(
            fn (array $item) => [Arr::first($item['by']) => Arr::first($item['totals'])], // @phpstan-ignore-line
        );

        /** @var TSentryCrashFreeData $data */
        $data = $collection->toArray();

        return $data;
    }

    /**
     * @param  TSentryCrashFreeData  $data
     */
    protected function transform(array $data): float
    {
        $healthy = $data['errored'] + $data['healthy'];

        $total = max($healthy + $data['crashed'], 1);

        return round($healthy / $total * 100, $this->decimal);
    }
}
