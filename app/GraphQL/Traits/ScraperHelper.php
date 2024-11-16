<?php

namespace App\GraphQL\Traits;

use App\Exceptions\NotFoundHttpException;
use App\Models\Tenant;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\Plain;
use Webmozart\Assert\Assert;

trait ScraperHelper
{
    /**
     * Issue a JWT token.
     */
    protected function issueJWT(int|string $id): string
    {
        $now = now()->toImmutable();

        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        return app('jwt.builder')
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->addWeek())
            ->withClaim('sid', (string) $id)
            ->withClaim('cid', $tenant->id)
            ->withClaim('oid', (string) $tenant->owner->id)
            ->getToken(
                app('jwt')->signer(),
                app('jwt')->signingKey(),
            )
            ->toString();
    }

    /**
     * Convert jwt payload to dataset.
     */
    protected function parseJWT(string $token): DataSet
    {
        if (empty($token)) {
            throw new NotFoundHttpException();
        }

        try {
            /** @var Plain $plan */
            $plan = app('jwt.parser')->parse($token);
        } catch (InvalidTokenStructure) {
            throw new NotFoundHttpException();
        }

        return $plan->claims();
    }
}
