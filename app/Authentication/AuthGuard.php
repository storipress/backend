<?php

namespace App\Authentication;

use App\Models\AccessToken;
use App\Models\Subscriber;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard as StatelessGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Sentry\State\Scope;
use Webmozart\Assert\Assert;

use function Sentry\configureScope;

class AuthGuard implements StatelessGuard
{
    protected ?Authenticatable $user = null;

    public function __construct(
        protected UserProvider $provider,
        protected Request $request,
    ) {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function guest(): bool
    {
        return ! $this->check();
    }

    /**
     * {@inheritDoc}
     */
    public function user(): ?Authenticatable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->request->input(
            'access_token',
            $this->request->bearerToken(),
        );

        if ($token === null && Str::startsWith($this->request->route()?->getName() ?: '', 'oauth.')) {
            $token = $this->request->query('state');
        }

        // @todo 待其他服務更新後，移除 backward compatibility
        if ($token === null) {
            if ($this->request->is('hocuspocus-webhook')) {
                $token = $this->request->json('payload.requestParameters.uid');
            } else {
                $token = $this->request->header('api-token') ?: $this->request->input('api-token');
            }
        }

        if (! is_string($token) || strlen($token) !== 46) {
            return null;
        }

        $checksum = substr($token, 40);

        $crc32 = base62_crc32(substr($token, 0, 40), 6, '0');

        if (! hash_equals($crc32, $checksum)) {
            return null;
        }

        $access = AccessToken::whereToken($token)->first();

        if ($access === null || $access->expires_at->isPast()) {
            return null;
        }

        $tokenable = $access->tokenable;

        Assert::implementsInterface($tokenable, Authenticatable::class);

        $tenantId = $this->request->route('client');

        if (is_string($tenantId)) {
            $method = match (get_class($tokenable)) {
                User::class => 'checkUser',
                Subscriber::class => 'checkSubscriber',
                Tenant::class => 'checkTenant',
                default => null,
            };

            if ($method === null || ! method_exists($this, $method)) {
                return null;
            }

            if (! $this->{$method}($tokenable, $tenantId)) {
                return null;
            }
        }

        $this->request->setLaravelSession(new SessionStore($access));

        $tokenable->access_token = $access; // @phpstan-ignore-line

        if ($tokenable instanceof User) {
            $this->setSentryUser($tokenable);
        } elseif ($tokenable instanceof Subscriber) {
            $this->setSentrySubscriber($tokenable);
        } elseif ($tokenable instanceof Tenant) {
            $this->setSentryTenant($tokenable);
        }

        return $this->user = $tokenable;
    }

    /**
     * Pre-check for tenant authentication.
     */
    protected function checkTenant(Tenant $tenant, string $tenantId): bool
    {
        return $tenant->id === $tenantId;
    }

    /**
     * Pre-check for user authentication.
     */
    protected function checkUser(User $user, string $tenantId): bool
    {
        return DB::connection($user->getConnectionName())
            ->table('tenant_user')
            ->where('tenant_id', '=', $tenantId)
            ->where('user_id', '=', $user->id)
            ->exists();
    }

    /**
     * Pre-check for subscriber authentication.
     */
    protected function checkSubscriber(Subscriber $subscriber, string $tenantId): bool
    {
        return DB::connection($subscriber->getConnectionName())
            ->table('subscriber_tenant')
            ->where('tenant_id', '=', $tenantId)
            ->where('subscriber_id', '=', $subscriber->id)
            ->exists();
    }

    protected function setSentryUser(User $user): void
    {
        configureScope(function (Scope $scope) use ($user) {
            $scope->setUser(
                [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->full_name,
                    'ip_address' => $this->request->ip(),
                    'type' => 'user',
                ],
            );
        });
    }

    protected function setSentrySubscriber(Subscriber $subscriber): void
    {
        configureScope(function (Scope $scope) use ($subscriber) {
            $scope->setUser(
                [
                    'id' => $subscriber->id,
                    'email' => $subscriber->email,
                    'name' => $subscriber->full_name,
                    'ip_address' => $this->request->ip(),
                    'type' => 'subscriber',
                ],
            );
        });
    }

    protected function setSentryTenant(Tenant $tenant): void
    {
        configureScope(function (Scope $scope) use ($tenant) {
            $scope->setUser(
                [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'ip_address' => $this->request->ip(),
                    'type' => 'tenant',
                ],
            );
        });
    }

    /**
     * {@inheritDoc}
     */
    public function id(): int|string|null
    {
        $id = $this->user()?->getAuthIdentifier();

        if (is_int($id) || is_string($id)) {
            return $id;
        }

        return null;
    }

    /**
     * {@inheritDoc}
     *
     * @param  array<string, string>  $credentials
     */
    public function validate(array $credentials = []): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user === null) {
            return false;
        }

        return $this->provider->validateCredentials($user, $credentials);
    }

    /**
     * {@inheritDoc}
     */
    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }
}
