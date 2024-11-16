<?php

namespace App\GraphQL\Mutations\Invitation;

use App\Exceptions\AccessDeniedHttpException;
use App\Exceptions\BadRequestHttpException;
use App\Exceptions\QuotaExceededHttpException;
use App\Models\Tenant;
use App\Models\Tenants\Invitation;
use App\Models\Tenants\User as TenantUser;
use App\Models\Tenants\UserActivity;
use App\Services\InvitationService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Webmozart\Assert\Assert;

class CreateInvitation extends InvitationMutation
{
    /**
     * @param array{
     *   email: string,
     *   role_id: string,
     *   desk_id: array<int, string>
     * } $args
     */
    public function __invoke($_, array $args): bool
    {
        $this->authorize('write', Invitation::class);

        /** @var Tenant $tenant */
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $role = find_role($args['role_id']);

        if (!$tenant->owner->onTrial() && !$tenant->owner->subscribed()) {
            if (!in_array($role->name, ['contributor', 'author'], true)) {
                throw new QuotaExceededHttpException();
            }
        }

        $manipulator = TenantUser::find(auth()->user()?->getAuthIdentifier());

        Assert::isInstanceOf($manipulator, TenantUser::class);

        if ($role->level >= find_role($manipulator->role)->level) {
            throw new AccessDeniedHttpException();
        }

        $args['email'] = Str::lower($args['email']);

        if (!Cache::add(hmac(Arr::only($args, ['email'])), true, 1)) {
            Log::debug('Create an invitation with same email in a short time.', [
                'tenant' => $tenant->getKey(),
                'args' => $args['email'],
            ]);

            throw new BadRequestHttpException();
        }

        UserActivity::log(
            name: 'invitation.create',
            data: [
                'email' => $args['email'],
            ],
        );

        (new InvitationService())
            ->setInviterId((string) $manipulator->id)
            ->setEmail($args['email'])
            ->setRoleId($args['role_id'])
            ->setDeskIds($args['desk_id'])
            ->invite();

        return true;
    }
}
