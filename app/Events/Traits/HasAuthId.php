<?php

namespace App\Events\Traits;

trait HasAuthId
{
    protected function setAuthIdIfRequired(): void
    {
        if (! property_exists($this, 'authId')) {
            return;
        }

        if (! $this->is_positive_int($this->authId)) {
            $this->authId = ((int) auth()->id()) ?: null;
        }
    }

    /**
     * Check is the given variable is a positive integer.
     *
     * @return ($data is positive-int ? true : false)
     */
    protected function is_positive_int(mixed $data): bool
    {
        return is_int($data) && $data > 0;
    }
}
