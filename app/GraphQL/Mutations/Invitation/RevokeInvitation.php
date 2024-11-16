<?php

namespace App\GraphQL\Mutations\Invitation;

use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenants\Invitation;
use App\Models\Tenants\UserActivity;
use Exception;

class RevokeInvitation extends InvitationMutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Invitation
    {
        $this->authorize('write', Invitation::class);

        /** @var Invitation|null $invitation */
        $invitation = Invitation::find($args['id']);

        if ($invitation === null) {
            throw new NotFoundHttpException();
        }

        try {
            $deleted = $invitation->delete();
        } catch (Exception $e) {
            throw new InternalServerErrorHttpException();
        }

        if (! $deleted) {
            throw new InternalServerErrorHttpException();
        }

        UserActivity::log(
            name: 'invitation.revoke',
            subject: $invitation,
        );

        return $invitation;
    }
}
