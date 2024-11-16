<?php

namespace App\GraphQL\Mutations\Invitation;

use App\Exceptions\NotFoundHttpException;
use App\Mail\UserInviteMail;
use App\Models\Tenant;
use App\Models\Tenants\Invitation;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Facades\Mail;
use Webmozart\Assert\Assert;

class ResendInvitation extends InvitationMutation
{
    /**
     * @param  array<string, string>  $args
     */
    public function __invoke($_, array $args): Invitation
    {
        $this->authorize('write', Invitation::class);

        /** @var Invitation|null $invitation */
        $invitation = Invitation::find($args['id']);

        if ($invitation === null) {
            throw new NotFoundHttpException();
        }

        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        Mail::to($invitation->email)->send(
            new UserInviteMail(
                inviter: ($invitation->inviter?->full_name ?: $tenant->owner->full_name) ?: $tenant->name,
                email: $invitation->email,
            ),
        );

        UserActivity::log(
            name: 'invitation.resend',
            subject: $invitation,
        );

        return $invitation;
    }
}
