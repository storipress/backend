<?php

namespace App\Listeners\Entity\Domain;

use App\Events\Entity\Domain\CustomDomainCheckRequested;
use App\Events\Entity\Domain\CustomDomainInitialized;
use App\Models\CustomDomain;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Net_DNS2_Exception;
use Net_DNS2_Resolver;
use Net_DNS2_RR;
use Sentry\State\Scope;
use Throwable;
use Webmozart\Assert\Assert;

use function Sentry\captureException;
use function Sentry\withScope;

class CheckDnsRecord implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    public Net_DNS2_Resolver $resolver;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        $this->resolver = new Net_DNS2_Resolver([
            'nameservers' => [
                '1.1.1.1',
                '1.0.0.1',
                '8.8.8.8',
                '8.8.4.4',
            ],
            'timeout' => 1,
            'ns_random' => true,
            'cache_type' => 'none',
            'strict_query_mode' => true,
        ]);
    }

    /**
     * Handle the event.
     */
    public function handle(CustomDomainInitialized|CustomDomainCheckRequested $event): void
    {
        $tenant = Tenant::with('custom_domains')->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $domains = $tenant->custom_domains->where('ok', '=', false);

        $domains->each(function (CustomDomain $domain) {
            try {
                $result = $this->check(
                    $domain->hostname,
                    $domain->type,
                    $domain->value,
                );

                $domain->ok = $result === true;

                if (is_not_empty_string($result)) {
                    $domain->error = $result;
                } else {
                    $domain->error = null;
                }

                $domain->last_checked_at = now();

                $domain->save();
            } catch (Throwable $e) {
                withScope(function (Scope $scope) use ($e, $domain) {
                    $scope->setContext('domain', $domain->toArray());

                    captureException($e);
                });
            }
        });

        if ($domains->where('ok', '=', false)->isNotEmpty()) {
            $attempts = $this->attempts();

            if ($attempts <= 4) {
                $this->release(15 * $attempts);
            }
        }
    }

    protected function check(string $hostname, string $type, string $expected): true|string
    {
        try {
            $result = $this->resolver->query($hostname, $type);
        } catch (Net_DNS2_Exception $e) {
            return sprintf('internal_error[%s]', $e->getMessage());
        }

        $count = count($result->answer);

        if ($count === 0) {
            return 'record_not_found';
        }

        if ($type === 'A' && $count > 2) {
            return 'too_many_records';
        }

        if ($count > 1) {
            return 'too_many_records';
        }

        $record = $result->answer[0];

        Assert::isInstanceOf($record, Net_DNS2_RR::class);

        $data = $record->asArray()['rdata'];

        $value = trim($data, '."');

        $passed = $value === $expected;

        if ($passed || ($type === 'A' && $value === '76.223.72.197')) {
            return true;
        }

        return 'invalid_value';
    }
}
