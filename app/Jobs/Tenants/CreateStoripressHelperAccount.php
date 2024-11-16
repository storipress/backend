<?php

namespace App\Jobs\Tenants;

use App\Enums\User\Status;
use App\Models\Tenants\User as TenantUser;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

final class CreateStoripressHelperAccount implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected TenantWithDatabase $tenant;

    /**
     * Create a new job instance.
     */
    public function __construct(TenantWithDatabase $tenant)
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
        $user = User::whereEmail('hello@storipress.com')->first();

        if (is_null($user)) {
            throw new RuntimeException(
                'Missing Storipress helper account!',
            );
        }

        $user->tenants()->attach($this->tenant->getTenantKey());

        $this->tenant->run(function () use ($user) {
            $helper = new TenantUser(array_merge(
                $user->only([
                    'id',
                ]),
                [
                    'role' => 'author',
                    'status' => Status::suspended(),
                    'suspended_at' => now(),
                ],
            ));

            $helper->saveQuietly();
        });
    }
}
