<?php

namespace App\Services;

use App\Enums\Credit\State;
use App\Events\Entity\Tenant\UserJoined;
use App\Mail\UserInviteMail;
use App\Models\Credit;
use App\Models\Tenant;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Invitation;
use App\Models\Tenants\User as TenantUser;
use App\Models\Tenants\UserActivity;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Segment\Segment;
use Webmozart\Assert\Assert;

class InvitationService
{
    protected string $inviterId;

    protected string $email;

    protected string $roleId;

    /**
     * @var array<int, string>
     */
    protected array $deskIds;

    /**
     * Perform invitation.
     */
    public function invite(): bool
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $user = User::whereEmail($this->email)->first();

        if ($user !== null) {
            $this->inviteExistentUserToPublication($user);
        } else {
            $this->inviteNonExistentUserToPublication();

            $this->addCreditToPublicationOwner();

            $this->addEmailToPendingList();

            Segment::track([
                'userId' => $this->inviterId,
                'event' => 'tenant_invitation_sent',
                'properties' => [
                    'tenant_uid' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'user_role' => find_role($this->roleId)->name,
                    'assigned_desks' => $this->deskIds,
                    'is_assigned_all_desks' => Desk::root()->count() === count($this->deskIds),
                ],
                'context' => [
                    'groupId' => $tenant->id,
                ],
            ]);
        }

        $inviter = User::find($this->inviterId);

        Assert::isInstanceOf($inviter, User::class);

        Mail::to($this->email)->send(
            new UserInviteMail(
                inviter: $inviter->full_name ?: $tenant->name,
                email: $this->email,
                exist: $user !== null,
            ),
        );

        return true;
    }

    /**
     * Invite an existent user to publication.
     */
    protected function inviteExistentUserToPublication(User $base): void
    {
        $role = find_role($this->roleId)->name;

        $user = new TenantUser([
            'id' => $base->getKey(),
            'role' => $role,
        ]);

        Assert::true($user->saveQuietly());

        if (! empty($this->deskIds)) {
            $user->desks()->attach($this->deskIds);
        }

        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $tenant->users()->attach($base, [
            'role' => $role,
        ]);

        UserJoined::dispatch($tenant->id, $user->id);

        Segment::track([
            'userId' => (string) $user->id,
            'event' => 'tenant_joined',
            'properties' => [
                'tenant_uid' => $tenant->id,
                'tenant_name' => $tenant->name,
                'user_role' => $role,
                'invited' => true,
            ],
            'context' => [
                'groupId' => $tenant->id,
            ],
        ]);
    }

    /**
     * Invite a new user to publication.
     */
    protected function inviteNonExistentUserToPublication(): void
    {
        $invitation = new Invitation([
            'email' => $this->email,
            'role_id' => $this->roleId,
            'created_at' => now(),
        ]);

        $invitation->inviter()->associate($this->inviterId);

        Assert::true($invitation->save());

        if (! empty($this->deskIds)) {
            $invitation->desks()->sync($this->deskIds);
        }

        UserActivity::log(
            name: 'invitation.create',
            subject: $invitation,
        );
    }

    /**
     * Add email to pending list.
     */
    protected function addEmailToPendingList(): void
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $tenantId = $tenant->getTenantKey();

        tenancy()->central(function () use ($tenantId) {
            $key = sprintf('invitation-%s', md5($this->email));

            $ids = Cache::get($key, []);

            Assert::isArray($ids);

            $ids[] = $tenantId;

            Cache::forever($key, $ids);
        });
    }

    /**
     * Add credit to publication owner.
     */
    protected function addCreditToPublicationOwner(): void
    {
        $role = find_role($this->roleId)->name;

        if (! in_array($role, ['admin', 'editor'], true)) {
            return; // only admin and editor can earn credits
        }

        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $credits = Credit::whereUserId($tenant->user_id)
            ->whereEarnedFrom('invitation')
            ->get();

        $earns = $credits->filter(function (Credit $credit) {
            return State::available()->is($credit->state) || State::used()->is($credit->state);
        });

        if ($earns->sum('amount') >= 60_00) {
            return; // user already earned $60 credits.
        }

        $invites = $credits->filter(function (Credit $credit) {
            $data = $credit->data;

            if ($data === null) {
                return false;
            } elseif (! isset($data['email'])) {
                return false;
            }

            return $data['email'] === $this->email;
        });

        if ($invites->isNotEmpty()) {
            return;
        }

        Credit::create([
            'user_id' => $tenant->user_id,
            'amount' => 20_00,
            'earned_from' => 'invitation',
            'data' => [
                'tenant' => $tenant->getTenantKey(),
                'email' => $this->email,
            ],
        ]);
    }

    public function setInviterId(string $inviterId): InvitationService
    {
        $this->inviterId = $inviterId;

        return $this;
    }

    public function setEmail(string $email): InvitationService
    {
        $this->email = $email;

        return $this;
    }

    public function setRoleId(string $roleId): InvitationService
    {
        $this->roleId = $roleId;

        return $this;
    }

    /**
     * @param  string[]  $deskIds
     */
    public function setDeskIds(array $deskIds): InvitationService
    {
        $this->deskIds = $deskIds;

        return $this;
    }
}
