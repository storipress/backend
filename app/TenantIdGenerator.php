<?php

namespace App;

use App\Models\Tenant;
use Exception;
use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\UniqueIdentifierGenerator;
use Webmozart\Assert\Assert;

final class TenantIdGenerator implements UniqueIdentifierGenerator
{
    /**
     * Unique identifier generator.
     *
     * @param  Tenant  $resource
     *
     * @throws Exception
     */
    public static function generate($resource): string
    {
        $env = config('app.env');

        Assert::stringNotEmpty($env);

        return sprintf(
            '%s%s',
            Str::upper(Str::substr($env, 0, 1)),
            Str::upper(Str::random(8)),
        );
    }
}
