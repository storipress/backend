<?php

namespace App\GraphQL\Mutations\CustomDomain;

use App\Enums\CustomDomain\Group;
use App\Events\Entity\Domain\CustomDomainInitialized;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\CustomDomain;
use App\Models\Tenant;
use Generator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Postmark\Models\PostmarkException;
use Webmozart\Assert\Assert;

class InitializeCustomDomain extends Mutation
{
    /**
     * @param  array{
     *     site: string|null,
     *     mail: string|null,
     *     redirect: array<int, string>,
     * }  $args
     * @return array{
     *     site: array<CustomDomain>,
     *     mail: array<CustomDomain>,
     *     redirect: array<CustomDomain>,
     * }
     */
    public function __invoke($_, array $args): array
    {
        $this->authorize('write', CustomDomain::class);

        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        if ($tenant->plan === 'free') {
            throw new HttpException(ErrorCode::CUSTOM_DOMAIN_PAID_REQUIRED);
        }

        $site = Str::lower($args['site'] ?: '');

        $mail = Str::lower($args['mail'] ?: '');

        $redirects = Arr::map(
            array_unique($args['redirect']),
            fn (string $redirect) => Str::lower($redirect),
        );

        if ($site && in_array($site, $redirects, true)) {
            throw new HttpException(ErrorCode::CUSTOM_DOMAIN_DUPLICATED, [
                'domain' => $site,
            ]);
        }

        if (count($redirects) !== count($args['redirect'])) {
            throw new HttpException(ErrorCode::CUSTOM_DOMAIN_DUPLICATED, [
                'domain' => array_diff_key($args['redirect'], $redirects),
            ]);
        }

        $domains = $redirects;

        if ($site) {
            $domains[] = $site;
        }

        if ($mail) {
            $domains[] = $mail;
        }

        /** @var array<int, string> $exists */
        $exists = CustomDomain::whereIn('domain', $domains)->pluck('domain')->toArray();

        if (count($exists) > 0) {
            throw new HttpException(ErrorCode::CUSTOM_DOMAIN_CONFLICT, [
                'domain' => array_unique($exists),
            ]);
        }

        $data = ['site' => [], 'mail' => [], 'redirect' => []];

        if ($mail) {
            $data['mail'] = iterator_to_array($this->mail($mail, $tenant));
        }

        if ($site) {
            $data['site'] = [$this->site($site, $tenant)];
            $data['redirect'] = iterator_to_array($this->redirect($redirects, $tenant));
        }

        CustomDomainInitialized::dispatch($tenant->id);

        return $data;
    }

    protected function site(string $domain, Tenant $tenant): CustomDomain
    {
        $attributes = array_merge(
            $this->alias($domain),
            [
                'group' => Group::site(),
            ],
        );

        return $this->save($attributes, $tenant);
    }

    /**
     * @return Generator<CustomDomain>
     */
    protected function mail(string $domain, Tenant $tenant): Generator
    {
        try {
            $postmark = app('postmark.account')->createDomain($domain);
        } catch (PostmarkException $e) {
            $message = $e->getMessage();

            if (Str::contains($message, 'use public domain', true)) {
                throw new HttpException(ErrorCode::CUSTOM_DOMAIN_INVALID_VALUE);
            } else {
                throw $e;
            }
        }

        $tenant->update([
            'postmark_id' => $postmark->getID(),
        ]);

        yield $this->save([
            'group' => Group::mail(),
            'domain' => Str::lower($domain),
            'hostname' => $postmark->getDKIMPendingHost() ?: $postmark->getDKIMHost(),
            'type' => 'TXT',
            'value' => $postmark->getDKIMPendingTextValue() ?: $postmark->getDKIMTextValue(),
        ], $tenant);

        yield $this->save([
            'group' => Group::mail(),
            'domain' => Str::lower($domain),
            'hostname' => $postmark->getReturnPathDomain() ?: sprintf('pm-bounces.%s', $postmark->getName()),
            'type' => 'CNAME',
            'value' => $postmark->getReturnPathDomainCNAMEValue(),
        ], $tenant);
    }

    /**
     * @param  array<int, string>  $domains
     * @return Generator<CustomDomain>
     */
    protected function redirect(array $domains, Tenant $tenant): Generator
    {
        foreach ($domains as $domain) {
            $attributes = array_merge(
                $this->alias($domain),
                [
                    'group' => Group::redirect(),
                ],
            );

            yield $this->save($attributes, $tenant);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function alias(string $domain): array
    {
        $isTLD = $this->isTLD($domain);

        return [
            'domain' => Str::lower($domain),
            'hostname' => Str::lower($domain),
            'type' => $isTLD ? 'A' : 'CNAME',
            'value' => $isTLD ? '13.248.202.255' : 'cdn.storipress.com',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function save(array $attributes, Tenant $tenant): CustomDomain
    {
        $attributes['tenant_id'] = $tenant->id;

        return CustomDomain::create($attributes)->refresh();
    }

    protected function isTLD(string $domain): bool
    {
        $tld = app('pdp.rules')
            ->resolve($domain)
            ->registrableDomain()
            ->toString();

        return $domain === $tld;
    }
}
