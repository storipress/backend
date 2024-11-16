<?php

namespace App\Jobs\Tenants;

use App\Models\Tenant;
use App\Models\Tenants\User as TenantUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

final class CreateOwnerAccount implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected Tenant $tenant;

    /**
     * Create a new job instance.
     */
    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->tenant->run(function () {
            $owner = new TenantUser([
                'id' => $this->tenant->owner->id,
                'role' => 'owner',
            ]);

            $owner->saveQuietly();
        });

        $subscription = $this->tenant->owner->subscription();

        if ($subscription === null) {
            return;
        }

        if ($subscription->ended()) {
            return;
        }

        $plan = Str::before($subscription->stripe_price ?: '', '-');

        $this->tenant->update(['plan' => $plan]);
    }
}
