<?php

namespace App\Console\Commands\Tenants;

use App\Mail\UserInviteMail;
use App\Models\Tenant;
use App\Models\Tenants\Invitation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendReminderInvitationEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invitation:remind';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'send remind email if user not accept invite after 3 days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now()->startOfDay()->subDays(3)->toImmutable();

        $range = [$now, $now->endOfDay()];

        tenancy()->runForMultiple(
            null,
            function (Tenant $tenant) use ($range) {
                if (!$tenant->initialized) {
                    return;
                }

                $invitations = Invitation::with('inviter')
                    ->whereBetween('created_at', $range)
                    ->get();

                foreach ($invitations as $invitation) {
                    Mail::to($invitation->email)->send(
                        new UserInviteMail(
                            inviter: ($invitation->inviter?->full_name ?: $tenant->owner->full_name) ?: $tenant->name,
                            email: $invitation->email,
                        ),
                    );
                }
            },
        );

        return self::SUCCESS;
    }
}
