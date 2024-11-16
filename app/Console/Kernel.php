<?php

namespace App\Console;

use App\Console\Commands\BuildReleaseEvents;
use App\Console\Commands\BuildScheduledArticle;
use App\Console\Commands\Monitor\RunMonitor;
use App\Console\Commands\RebuildTrialEndedPublications;
use App\Console\Commands\Report\ReportWeeklyAnalyticMetrics;
use App\Console\Commands\Report\ReportWeeklyCrashFree;
use App\Console\Commands\Subscriber\GatherDailyMetrics;
use App\Console\Commands\Tenants\RunArticleAutoPosting;
use App\Console\Commands\Tenants\SendReminderInvitationEmail;
use App\Console\Commands\Tenants\UpdatePlatformsProfiles;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Str;
use Laravel\Horizon\Console\SnapshotCommand;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Finder\Finder;
use Webmozart\Assert\Assert;

final class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @throws ReflectionException
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command(BuildScheduledArticle::class)
            ->everyMinute()
            ->runInBackground()
            ->onOneServer();

        $schedule->command(BuildReleaseEvents::class)
            ->everyMinute()
            ->runInBackground()
            ->onOneServer();

        $schedule->command(RunArticleAutoPosting::class)
            ->everyMinute()
            ->runInBackground()
            ->onOneServer();

        $schedule->command(RunMonitor::class)
            ->everyMinute()
            ->runInBackground()
            ->onOneServer();

        $schedule->command(SnapshotCommand::class)
            ->everyFiveMinutes()
            ->runInBackground()
            ->onOneServer();

        $schedule->command('cache:prune-stale-tags')
            ->hourly()
            ->runInBackground()
            ->onOneServer();

        $schedule->command(GatherDailyMetrics::class)
            ->daily()
            ->runInBackground()
            ->onOneServer();

        $schedule->command(SendReminderInvitationEmail::class)
            ->daily()
            ->runInBackground()
            ->onOneServer();

        $schedule->command(RebuildTrialEndedPublications::class)
            ->dailyAt('00:13')
            ->runInBackground()
            ->onOneServer();

        $schedule->command('domain-parser:refresh')
            ->dailyAt('04:00')
            ->runInBackground()
            ->onOneServer();

        $schedule->command(UpdatePlatformsProfiles::class)
            ->weeklyOn(1, '02:33')
            ->runInBackground()
            ->onOneServer();

        $schedule->command(ReportWeeklyCrashFree::class)
            ->weekly()
            ->fridays()
            ->environments('production')
            ->runInBackground()
            ->onOneServer();

        $schedule->command(ReportWeeklyAnalyticMetrics::class)
            ->weekly()
            ->mondays()
            ->environments('production')
            ->runInBackground()
            ->onOneServer();

        $this->loadSchedule($schedule);
    }

    /**
     * @throws ReflectionException
     */
    protected function loadSchedule(Schedule $schedule): void
    {
        $frequencies = [
            'everyFiveMinutes' => 'FiveMinutes',
            'everyTenMinutes' => 'TenMinutes',
            'everyFifteenMinutes' => 'FifteenMinutes',
            'hourly' => 'Hourly',
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
        ];

        $namespace = $this->app->getNamespace();

        $appPath = realpath(app_path());

        Assert::stringNotEmpty($appPath);

        foreach ($frequencies as $frequency => $dir) {
            $path = sprintf('%s/Schedules/%s', __DIR__, $dir);

            foreach ((new Finder)->in($path)->files() as $command) {
                $command = Str::of($command->getRealPath())
                    ->remove($appPath)
                    ->remove('.php')
                    ->ltrim('/')
                    ->replace('/', '\\')
                    ->prepend($namespace)
                    ->toString();

                if (!is_subclass_of($command, Command::class)) {
                    continue;
                }

                $class = new ReflectionClass($command);

                if ($class->isAbstract()) {
                    continue;
                }

                $parameters = [];

                if ($class->implementsInterface(Isolatable::class)) {
                    $parameters['--isolated'] = Command::SUCCESS;
                }

                $schedule->command($command, $parameters)
                    ->{$frequency}()
                    ->runInBackground()
                    ->onOneServer();
            }
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        $this->load(__DIR__ . '/Migrations');

        $this->load(__DIR__ . '/Schedules');

        if (app()->isLocal()) {
            $this->load(__DIR__ . '/Local');
        }

        if (app()->runningUnitTests()) {
            $this->load(__DIR__ . '/Testing');
        }
    }
}
