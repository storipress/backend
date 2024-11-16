<?php

namespace App\Authentication;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider as BaseUserProvider;
use RuntimeException;

use function Sentry\captureException;

class UserProvider implements BaseUserProvider
{
    /**
     * {@inheritDoc}
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        $this->reportAbuse();

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return $this->retrieveById($identifier);
    }

    /**
     * {@inheritDoc}
     */
    public function updateRememberToken(Authenticatable $user, $token): void
    {
        $this->reportAbuse();
    }

    /**
     * {@inheritDoc}
     *
     * @param  array<string, string>  $credentials
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $this->reportAbuse();

        return null;
    }

    /**
     * {@inheritDoc}
     *
     * @param  array<string, string>  $credentials
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $this->reportAbuse();

        return false;
    }

    protected function reportAbuse(): void
    {
        captureException(new RuntimeException('Misuse user provider.'));
    }
}
