<?php

namespace App\GraphQL\Mutations\Auth;

use App\Enums\AccessToken\Type;
use App\Enums\Credit\State;
use App\Events\Auth\SignedUp;
use App\Events\Entity\Tenant\UserJoined;
use App\Exceptions\InternalServerErrorHttpException;
use App\Mail\UserEmailVerifyMail;
use App\Models\AccessToken;
use App\Models\Credit;
use App\Models\Tenant;
use App\Models\Tenants\Invitation;
use App\Models\Tenants\User as TenantUser;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Segment\Segment;
use stdClass;
use Stripe\Exception\ApiErrorException;
use Webmozart\Assert\Assert;

final class SignUp extends Auth
{
    /**
     * @param  array{
     *     email: string,
     *     password: string,
     *     first_name?: string|null,
     *     last_name?: string|null,
     *     invite_token?: string,
     *     publication_name?: string|null,
     *     timezone?: string,
     *     appsumo_code?: string,
     *     checkout_id?: string,
     *     campaign?: stdClass,
     * }  $args
     * @return array<int|string>
     */
    public function __invoke($_, array $args): array
    {
        if (app()->isProduction()) {
            throw new InternalServerErrorHttpException();
        }

        $email = Str::lower($args['email']);

        /** @var string[] $inviteIds */
        $inviteIds = tenancy()->central(function () use ($email) {
            $key = sprintf('invitation-%s', md5($email));

            /** @var string[] $ids */
            $ids = Cache::get($key, []);

            if (empty($ids)) {
                return [];
            }

            $key = sprintf('invitation-flag-%s', md5($email));

            Cache::put($key, true, 3600);

            return $ids;
        });

        if (isset($args['appsumo_code'])) {
            $key = 'appsumo-' . $args['appsumo_code'];

            $known = Cache::get($key);

            if ($email === $known) {
                $user = User::whereEmail($email)->first();

                Assert::isInstanceOf($user, User::class);

                $user->update([
                    'password' => Hash::make($args['password']),
                    'first_name' => $args['first_name'] ?? null,
                    'last_name' => $args['last_name'] ?? null,
                ]);

                Cache::forget($key);
            }
        }

        if (isset($args['checkout_id'])) {
            $used = DB::table('subscriptions')
                ->where('stripe_id', '=', $args['checkout_id'])
                ->exists();

            if (!$used) {
                try {
                    $checkout = Cashier::stripe()
                        ->checkout
                        ->sessions
                        ->retrieve($args['checkout_id']);
                } catch (ApiErrorException) {
                    //
                }
            }
        }

        $user = $user ?? User::create([
            'email' => $email,
            'first_name' => $args['first_name'] ?? null,
            'last_name' => $args['last_name'] ?? null,
            'password' => Hash::make($args['password']),
            'signed_up_source' => empty($inviteIds) ? 'direct' : 'invite:' . implode(',', $inviteIds),
            'stripe_id' => isset($checkout) ? $checkout->customer : null,
        ]);

        Assert::isInstanceOf($user, User::class);

        UserActivity::log(
            name: 'auth.sign_up',
            userId: $user->id,
        );

        Segment::track([
            'userId' => (string) $user->id,
            'event' => 'user_signed_up',
            'properties' => [
                'invited' => !empty($inviteIds),
            ],
            'context' => [
                'campaign' => ((array) ($args['campaign'] ?? [])) ?: null,
            ],
        ]);

        if (isset($checkout)) {
            $user->subscriptions()->create([
                'name' => 'appsumo',
                'stripe_id' => $checkout->id,
                'stripe_status' => 'active',
                'stripe_price' => 'prophet',
                'quantity' => 1,
                'ends_at' => now()->addYears(),
            ]);
        }

        $publication = trim($args['publication_name'] ?? '');

        if (!empty($publication)) {
            $workspace = sprintf(
                '%s-%s',
                Str::limit(Str::slug($publication), 27, ''),
                Str::lower(Str::random(4)),
            );

            /** @var Tenant $tenant */
            $tenant = $user->tenants()->create([
                'user_id' => $user->getKey(),
                'name' => $publication,
                'workspace' => trim($workspace, '-'),
                'timezone' => $args['timezone'] ?? 'UTC',
                'invites' => [],
            ], [
                'role' => 'owner',
            ]);

            UserActivity::log(
                name: 'publication.create',
                subject: $tenant,
                userId: $user->id,
            );
        }

        Mail::to($user->email)->send(
            new UserEmailVerifyMail($user->email),
        );

        $this->addTenantUser($user);

        $token = $user->accessTokens()->create([
            'name' => 'sign-up',
            'token' => AccessToken::token(Type::user()),
            'abilities' => '*',
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'expires_at' => now()->addYears(5),
        ]);

        SignedUp::dispatch($user->id);

        return $this->responseWithToken($token);
    }

    /**
     * Add user to tenant if there is pending invitations.
     */
    protected function addTenantUser(User $user): void
    {
        $ids = tenancy()->central(function () use ($user) {
            $key = sprintf('invitation-%s', md5($user->email));

            return Cache::pull($key, []);
        });

        Assert::allString($ids);

        foreach ($ids as $id) {
            $tenant = Tenant::withTrashed()->with(['owner'])->find($id);

            if (!($tenant instanceof Tenant)) {
                continue;
            }

            if ($tenant->trashed()) {
                continue;
            }

            /** @var string|null $result */
            $result = $tenant->run(function () use ($user, $tenant) {
                $invitation = Invitation::whereEmail($user->email)->first();

                if ($invitation === null) {
                    return null;
                }

                if (TenantUser::whereId($user->getKey())->exists()) {
                    $invitation->delete();

                    return null;
                }

                $name = find_role($invitation->role_id)->name;

                $tenantUser = new TenantUser([
                    'id' => $user->getKey(),
                    'role' => $name,
                ]);

                Assert::true($tenantUser->saveQuietly());

                if ($invitation->desks->isNotEmpty()) {
                    $tenantUser->desks()->attach($invitation->desks);
                }

                $invitation->delete();

                Segment::track([
                    'userId' => (string) $user->id,
                    'event' => 'tenant_joined',
                    'properties' => [
                        'tenant_uid' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'user_role' => $name,
                        'invited' => true,
                    ],
                    'context' => [
                        'groupId' => $tenant->id,
                    ],
                ]);

                return $name;
            });

            if ($result === null) {
                continue;
            }

            $tenant->users()->attach($user, [
                'role' => $result,
            ]);

            UserJoined::dispatch($tenant->id, $user->id);

            $earned = $tenant
                ->owner
                ->credits()
                ->where('earned_from', 'invitation')
                ->whereIn('state', [State::available(), State::used()])
                ->sum('amount');

            if ($earned >= 60_00) {
                continue; // user already earned $60 credits.
            }

            $credits = $tenant
                ->owner
                ->credits()
                ->where('earned_from', 'invitation')
                ->where('state', State::draft())
                ->get()
                ->filter(function (Credit $credit) use ($tenant, $user) {
                    $data = $credit->data;

                    if (empty($data) || empty($data['tenant']) || empty($data['email'])) {
                        return false;
                    }

                    return $data['tenant'] === $tenant->getTenantKey() &&
                           $data['email'] === $user->email;
                })
                ->values();

            if ($credits->isEmpty()) {
                continue;
            }

            Assert::count($credits, 1);

            $credit = $credits->first();

            Assert::isInstanceOf($credit, Credit::class);

            $data = $credit->data;

            $data['user_id'] = $user->getKey();

            if (($earned + $credit->amount) > 60_00) {
                $credit->amount = 60_00 - $earned;
            }

            $credit->update([
                'state' => State::available(),
                'data' => $data,
                'earned_at' => now(),
            ]);
        }
    }
}
