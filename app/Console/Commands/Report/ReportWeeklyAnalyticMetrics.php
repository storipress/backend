<?php

namespace App\Console\Commands\Report;

use AkkiIo\LaravelGoogleAnalytics\Exceptions\InvalidPeriod;
use AkkiIo\LaravelGoogleAnalytics\LaravelGoogleAnalytics;
use AkkiIo\LaravelGoogleAnalytics\Period;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Stage;
use App\Models\Tenants\UserActivity;
use App\Models\User;
use Google\Analytics\Data\V1beta\Filter\StringFilter\MatchType;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Webmozart\Assert\Assert;

class ReportWeeklyAnalyticMetrics extends ReportAnalyticMetrics
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:weekly-analytic-metrics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Report weekly goal analytic metrics';

    protected int $decimal = 2;

    /**
     * Matomo API client.
     */
    protected PendingRequest $matomo;

    protected int $appPropertyId;

    protected int $staticPropertyId;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var int $appPropertyId */
        $appPropertyId = config('services.google_analytics.app_property_id');

        /** @var int $staticPropertyId */
        $staticPropertyId = config('services.google_analytics.static_property_id');

        if (empty($appPropertyId) || empty($staticPropertyId)) {
            $this->error('Google Analytics is not configured');

            return self::FAILURE;
        }

        $this->appPropertyId = $appPropertyId;

        $this->staticPropertyId = $staticPropertyId;

        $content = file_get_contents(
            resource_path('notifications/slack/weekly-users-analysis-report.json'),
        );

        if (empty($content)) {
            $this->error('Fail to load the report template.');

            return self::FAILURE;
        }

        $list = [
            'draftCountThisWeek' => 0,
            'draftCountLastWeek' => 0,
            'publishedCountThisWeek' => 0,
            'publishedCountLastWeek' => 0,
            'activePublicationCountThisWeek' => 0,
            'activePublicationCountLastWeek' => 0,
            'userInvitedThisWeek' => 0,
            'userInvitedLastWeek' => 0,
        ];

        $thisWeekStart = now()->subWeek()->startOfWeek();
        $thisWeekEnd = $thisWeekStart->copy()->endOfWeek();
        $lastWeekStart = $thisWeekStart->copy()->subWeek();
        $lastWeekEnd = $thisWeekEnd->copy()->subWeek();

        tenancy()->runForMultiple(
            null,
            function (Tenant $tenant) use (&$list, $thisWeekStart, $thisWeekEnd, $lastWeekStart, $lastWeekEnd) {
                $draftStageId = Stage::default()->first()?->id;

                $activeThisWeek = UserActivity::whereBetween('occurred_at', [
                    $thisWeekStart,
                    $thisWeekEnd,
                ])->exists();

                if ($activeThisWeek) {
                    $list['activePublicationCountThisWeek']++;
                    $list['publishedCountThisWeek'] += $this->getPublishedCount($thisWeekStart, $thisWeekEnd);
                    $list['draftCountThisWeek'] += $this->getArticleStageCount($draftStageId, $thisWeekStart, $thisWeekEnd);
                }

                $activeLastWeek = UserActivity::whereBetween('occurred_at', [
                    $lastWeekStart,
                    $lastWeekEnd,
                ])->exists();

                if ($activeLastWeek) {
                    $list['activePublicationCountLastWeek']++;
                    $list['publishedCountLastWeek'] += $this->getPublishedCount($lastWeekStart, $lastWeekEnd);
                    $list['draftCountLastWeek'] += $this->getArticleStageCount($draftStageId, $lastWeekStart, $lastWeekEnd);
                }
            },
        );

        // User Experience
        $activeUsers = $this->calculate(
            $this->getActiveUsers($thisWeekStart, $thisWeekEnd),
            $this->getActiveUsers($lastWeekStart, $lastWeekEnd),
        );

        $sessionsPerUser = $this->calculate(
            $this->getSessionsPerUser($thisWeekStart, $thisWeekEnd),
            $this->getSessionsPerUser($lastWeekStart, $lastWeekEnd),
        );

        $usersInvited = $this->calculate(
            $this->getUserInvitedCount($thisWeekStart, $thisWeekEnd),
            $this->getUserInvitedCount($lastWeekStart, $lastWeekEnd),
        );

        // Reader Mobile Pagespeed
        //$mobileScoreThisWeek = $this->getMobilePerformanceScore();

        //$lowestMobileScore = $this->calculate($mobileScoreThisWeek, 0);

        // Content
        $draftsPerPublication = $this->calculateDraftsPerPublication($list);

        $publishedPerPublication = $this->calculatePublishedPerPublication($list);

        $weeklyPageViews = $this->calculate(
            $this->getPageViews($thisWeekStart, $thisWeekEnd),
            $this->getPageViews($lastWeekStart, $lastWeekEnd),
        );

        $pageViewsPerSession = $this->calculate(
            $this->getPageViewsPerSession($thisWeekStart, $thisWeekEnd),
            $this->getPageViewsPerSession($lastWeekStart, $lastWeekEnd),
        );

        // top N pages
        $topNPages = $this->getTopNPages(3, $thisWeekStart, $thisWeekEnd);

        $mapping = [
            '{date}' => now()->subWeek()->endOfWeek()->toFormattedDateString(),
        ];

        $mapping = array_merge(
            $mapping,
            // User Experience
            $this->createMapping('active_users', $activeUsers),
            $this->createMapping('sessions_per_user', $sessionsPerUser),
            $this->createMapping('users_invited', $usersInvited),
            // Reader Mobile Pagespeed
            //$this->createMapping('mobile_page_score', $lowestMobileScore),
            // Content
            $this->createMapping('drafts_per_pub', $draftsPerPublication),
            $this->createMapping('published_per_pub', $publishedPerPublication),
            // PageViews
            $this->createMapping('weekly_pageviews', $weeklyPageViews),
            $this->createMapping('pageviews_per_session', $pageViewsPerSession),

            // top N pages
            $this->createTopNPagesMapping($topNPages),
        );

        $this->sendToSlack(strtr($content, $mapping));

        return self::SUCCESS;
    }

    /**
     * get mobile performance score
     */
    protected function getMobilePerformanceScore(): int
    {
        // TODO: PageSpeed Insight

        return 0;
    }

    protected function getPublishedCount(Carbon $start, Carbon $end): int
    {
        return Article::whereBetween('created_at', [$start, $end])
            ->whereNotNull('published_at')
            ->count();
    }

    protected function getArticleStageCount(?int $stageId, Carbon $start, Carbon $end): int
    {
        return Article::where('stage_id', $stageId)
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    protected function getUserInvitedCount(Carbon $start, Carbon $end): int
    {
        return User::whereBetween('created_at', [$start, $end])
            ->where('signed_up_source', 'LIKE', 'invite:%')
            ->count();
    }

    /**
     * @throws InvalidPeriod
     */
    protected function getActiveUsers(Carbon $startDate, Carbon $endDate): int
    {
        $response = $this->analytic()
            ->setPropertyId($this->appPropertyId)
            ->dateRange(Period::create($startDate, $endDate))
            ->metric('activeUsers')
            ->get()
            ->table;

        /** @var int $activeUsers */
        $activeUsers = Arr::get($response, '0.activeUsers', 0);

        return $activeUsers;
    }

    /**
     * @throws InvalidPeriod
     */
    protected function getSessionsPerUser(Carbon $startDate, Carbon $endDate): float
    {
        $response = $this->analytic()
            ->setPropertyId($this->appPropertyId)
            ->dateRange(Period::create($startDate, $endDate))
            ->metric('sessionsPerUser')
            ->get()
            ->table;

        /** @var float $sessionsPerUser */
        $sessionsPerUser = Arr::get($response, '0.sessionsPerUser', 0.0);

        return $sessionsPerUser;
    }

    protected function getPageViewsPerSession(Carbon $startDate, Carbon $endDate): float
    {
        $response = $this->analytic()
            ->setPropertyId($this->staticPropertyId)
            ->dateRange(Period::create($startDate, $endDate))
            ->metric('screenPageViewsPerSession')
            ->get()
            ->table;

        /** @var float $pageViewsPerSession */
        $pageViewsPerSession = Arr::get($response, '0.screenPageViewsPerSession', 0.0);

        return $pageViewsPerSession;
    }

    protected function getPageViews(Carbon $startDate, Carbon $endDate): float
    {
        $response = $this->analytic()
            ->setPropertyId($this->staticPropertyId)
            ->dateRange(Period::create($startDate, $endDate))
            ->metric('screenPageViews')
            ->get()
            ->table;

        /** @var float $pageViews */
        $pageViews = Arr::get($response, '0.screenPageViews', 0.0);

        return $pageViews;
    }

    /**
     * @return array<int, array{title:string, url:string, views:int}>
     *
     * @throws InvalidPeriod
     * @throws \Google\ApiCore\ApiException
     * @throws \Google\ApiCore\ValidationException
     */
    protected function getTopNPages(int $limit, Carbon $startDate, Carbon $endDate): array
    {
        $result = [];

        $pages = $this->analytic()
            ->setPropertyId($this->staticPropertyId)
            ->dateRange(Period::create($startDate, $endDate))
            ->dimensions('pageTitle', 'hostName')
            ->metric('screenPageViews')
            ->limit($limit)
            ->orderByMetricDesc('screenPageViews')
            ->get()
            ->table;

        /** @var array{pageTitle:string, screenPageViews:int} $page */
        foreach ($pages as $page) {
            $response = $this->analytic()
                ->setPropertyId($this->staticPropertyId)
                ->dateRange(Period::create($startDate, $endDate))
                ->dimensions('pageTitle', 'hostName', 'pageLocation')
                ->metric('screenPageViews')
                ->whereDimension('pageTitle', MatchType::EXACT, $page['pageTitle'])
                ->limit(1)
                ->orderByMetricDesc('screenPageViews')
                ->get()
                ->table;

            /** @var string $url */
            $url = Arr::get($response, '0.pageLocation', '');

            /** @var string $url */
            $url = Str::before($url, '?');

            $result[] = [
                'title' => strip_tags($page['pageTitle']),
                'views' => $page['screenPageViews'],
                'url' => $url,
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, array{title:string, url:string, views:int}>  $topNPages
     * @return array<string, string|int>
     */
    protected function createTopNPagesMapping(array $topNPages): array
    {
        $mapping = [];

        foreach ($topNPages as $key => $page) {
            $mapping['{top'.($key + 1).'_title}'] = $page['title'];
            $mapping['{top'.($key + 1).'_visits}'] = $page['views'];
            $mapping['{top'.($key + 1).'_url}'] = $page['url'];
        }

        return $mapping;
    }

    /**
     * @param  array{value: float, diff: float, percentage: float}  $data
     * @return array<string, string|float>
     */
    protected function createMapping(string $name, array $data, bool $emojiReverse = false): array
    {
        $percentage = $data['percentage'] >= 0
            ? '+'.number_format($data['percentage'] * 100, 2).'%'
            : number_format($data['percentage'] * 100, 2).'%';

        $diff = $data['diff'] >= 0
            ? '+'.number_format($data['diff'], 2)
            : number_format($data['diff'], 2);

        return [
            '{'.$name.'}' => number_format($data['value'], 2),
            '{'.$name.'_percentage}' => $percentage,
            '{'.$name.'_diff}' => $diff,
            '{'.$name.'_emoji}' => ($data['diff'] >= 0 && ! $emojiReverse) ? '💹' : '‼️',
        ];
    }

    /**
     * @return array{value: float, diff: float, percentage: float}
     */
    protected function calculate(float|int $latest, float|int $compareTo): array
    {
        return [
            'value' => round($latest, $this->decimal),
            'percentage' => round(
                ($latest - $compareTo) / (empty($compareTo) ? 1 : $compareTo),
                $this->decimal + 2,
            ),
            'diff' => round($latest - $compareTo, $this->decimal),
        ];
    }

    /**
     * @param  array{draftCountThisWeek:int, draftCountLastWeek:int, activePublicationCountThisWeek:int, activePublicationCountLastWeek:int}  $list
     * @return array{value: float, diff: float, percentage: float}
     */
    protected function calculateDraftsPerPublication(array $list): array
    {
        return $this->calculate(
            $list['draftCountThisWeek'] / ($list['activePublicationCountThisWeek'] === 0 ? 1 : $list['activePublicationCountThisWeek']),
            $list['draftCountLastWeek'] / ($list['activePublicationCountLastWeek'] === 0 ? 1 : $list['activePublicationCountLastWeek']),
        );
    }

    /**
     * @param  array{publishedCountThisWeek:int, publishedCountLastWeek:int, activePublicationCountThisWeek:int, activePublicationCountLastWeek:int}  $list
     * @return array{value: float, diff: float, percentage: float}
     */
    protected function calculatePublishedPerPublication(array $list): array
    {
        return $this->calculate(
            $list['publishedCountThisWeek'] / ($list['activePublicationCountThisWeek'] === 0 ? 1 : $list['activePublicationCountThisWeek']),
            $list['publishedCountLastWeek'] / ($list['activePublicationCountLastWeek'] === 0 ? 1 : $list['activePublicationCountLastWeek']),
        );
    }

    /**
     * @param  array{userInvitedThisWeek:int, userInvitedLastWeek:int}  $list
     * @return array{value: float, diff: float, percentage: float}
     */
    protected function calculateUserInvited(array $list): array
    {
        return $this->calculate($list['userInvitedThisWeek'], $list['userInvitedLastWeek']);
    }

    protected function analytic(): LaravelGoogleAnalytics
    {
        $encoded = config('laravel-google-analytics.service_account_credentials_json');

        Assert::stringNotEmpty($encoded);

        $decoded = base64_decode($encoded, true);

        Assert::stringNotEmpty($decoded);

        $credentials = json_decode($decoded, true);

        Assert::isArray($credentials);

        // @phpstan-ignore-next-line
        return (new LaravelGoogleAnalytics())->setCredentials($credentials);
    }
}
