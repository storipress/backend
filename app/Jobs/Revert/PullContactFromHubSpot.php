<?php

namespace App\Jobs\Revert;

use App\Models\Tenant;
use App\Models\Tenants\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

class PullContactFromHubSpot implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $tenantId,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $token = config('services.revert.token');

        if (! is_not_empty_string($token)) {
            return;
        }

        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($this->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) use ($token) {
            $customer = sprintf('%s-hubspot', $tenant->id);

            $revert = app('revert')
                ->setToken($token)
                ->setCustomerId($customer)
                ->contact();

            $options = [
                'pageSize' => '100',
            ];

            $contacts = [];

            do {
                $deals = $revert->list($options);

                foreach ($deals['data'] as $contact) {
                    $email = Str::lower($contact->email);

                    $contacts[$email] = $contact->remoteId;
                }

                if ($deals['pagination']->next) {
                    $options['cursor'] = $deals['pagination']->next;
                }
            } while ($deals['pagination']->next);

            foreach (Subscriber::lazyById(100) as $subscriber) {
                if (! isset($contacts[$subscriber->email])) {
                    continue;
                }

                $subscriber->update([
                    'hubspot_id' => $contacts[$subscriber->email],
                ]);
            }
        });
    }
}
