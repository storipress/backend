<?php

namespace App\Models\Attributes;

use App\Models\Tenants\Article;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Subscriber;
use App\Models\Tenants\Tag;
use App\Models\Tenants\User;
use Hashids\Hashids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Webmozart\Assert\Assert;

trait StringIdentify
{
    public function getSidAttribute(): string
    {
        $id = $this->getKey();

        Assert::true(is_int($id) || is_string($id));

        return $this->hashids()->encode($id);
    }

    /**
     * Scope a query to only include popular users.
     *
     * @param  Builder<Article|Desk|Tag>  $query
     * @return Builder<Article|Desk|Tag>
     */
    public function scopeSid(Builder $query, string $sid): Builder
    {
        return $query->where(
            'id',
            '=',
            Arr::first($this->hashids()->decode($sid), null, 0),
        );
    }

    /**
     * Get hashids instance.
     */
    protected function hashids(): Hashids
    {
        /** @var string $scope */
        $scope = tenant('id') ?: 'CENTRAL';

        if ($this instanceof User || $this instanceof Subscriber) {
            $scope = 'CENTRAL';
        }

        return new Hashids(
            sprintf('%s-%s', $scope, class_basename($this)),
            property_exists($this, 'minSidLength') ? $this->minSidLength : 8,
            '1234567890abcdefghijklmnopqrstuvwxyz',
        );
    }
}
