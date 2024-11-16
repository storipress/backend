<?php

namespace App\Jobs\Tenants;

use App\Models\Tenant;
use App\Models\Tenants\Desk;
use App\Services\InvitationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class InviteUsers implements ShouldQueue
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
     */
    public function handle(): void
    {
        $emails = $this->tenant->invites;

        if (empty($emails)) {
            return;
        }

        $ownerId = $this->tenant->owner->id;

        $this->tenant->run(function () use ($emails, $ownerId) {
            $role = find_role('author');

            /** @var array<int, string> $deskIds */
            $deskIds = Desk::pluck('id')->toArray();

            foreach ($emails as $email) {
                (new InvitationService())
                    ->setInviterId((string) $ownerId)
                    ->setEmail($email)
                    ->setRoleId((string) $role->id)
                    ->setDeskIds($deskIds)
                    ->invite();
            }
        });
    }
}
