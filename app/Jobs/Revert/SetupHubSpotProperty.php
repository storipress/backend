<?php

namespace App\Jobs\Revert;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SetupHubSpotProperty implements ShouldQueue
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
                ->property();

            $properties = $revert->list('contact');

            foreach ($properties as $property) {
                if ($property->name === 'sp_pain_points') {
                    return;
                }
            }

            $revert->create('contact', [
                'name' => 'sp_pain_points',
                'type' => 'string',
                'additional' => [
                    'label' => 'Pain Points',
                    'groupName' => 'Storipress',
                    'fieldType' => 'textarea',
                ],
            ]);
        });
    }
}
