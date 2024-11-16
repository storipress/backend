<?php

namespace App\Authentication;

use App\Models\AccessToken;
use App\Models\User;

trait Authenticatable
{
    public AccessToken $access_token;

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string
    {
        return $this->getKeyName();
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier(): int|string
    {
        return $this->{$this->getAuthIdentifierName()};
    }

    /**
     * Get the unique broadcast identifier for the user.
     */
    public function getAuthIdentifierForBroadcasting(): int|string
    {
        return $this->getAuthIdentifier();
    }

    /**
     * Get the password for the user.
     */
    public function getAuthPassword(): ?string
    {
        if ($this instanceof User) {
            return $this->password;
        }

        return null;
    }

    /**
     * Get the token value for the "remember me" session.
     */
    public function getRememberToken(): ?string
    {
        return null;
    }

    /**
     * Set the token value for the "remember me" session.
     *
     * @param  string  $value
     * @return void
     */
    public function setRememberToken($value)
    {
        //
    }

    /**
     * Get the column name for the "remember me" token.
     */
    public function getRememberTokenName(): ?string
    {
        return null;
    }
}
