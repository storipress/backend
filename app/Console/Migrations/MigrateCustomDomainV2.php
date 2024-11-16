<?php

namespace App\Console\Migrations;

use App\Enums\CustomDomain\Group;
use App\Models\CustomDomain;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;
use Webmozart\Assert\Assert;

use function Sentry\captureException;

class MigrateCustomDomainV2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:custom-domain-v2';

    protected ?Tenant $tenant = null;

    public function handle(): int
    {
        $tenants = Tenant::with('owner')
            ->initialized()
            ->lazyById(50);

        foreach ($tenants as $tenant) {
            if (empty($tenant->custom_domain)) {
                continue;
            }

            $this->tenant = $tenant;

            $postmarkId = $tenant->postmark['id'] ?? null;

            if (is_int($postmarkId)) {
                $this->mail($tenant, $postmarkId);
            }

            $this->site($tenant->custom_domain);

            $this->redirect($tenant->custom_domain);

            $this->tenant = null;
        }

        return static::SUCCESS;
    }

    protected function mail(Tenant $tenant, int $id): bool
    {
        try {
            $postmark = app('postmark.account')->getDomain($id);
        } catch (Throwable $e) {
            captureException($e);

            return false;
        }

        if (!$postmark->isDKIMVerified() || !$postmark->isReturnPathDomainVerified()) {
            return false;
        }

        $tenant->update([
            'postmark_id' => $id,
        ]);

        $this->save([
            'group' => Group::mail(),
            'hostname' => $postmark->getDKIMHost(),
            'type' => 'TXT',
            'value' => $postmark->getDKIMTextValue(),
        ]);

        $this->save([
            'group' => Group::mail(),
            'hostname' => $postmark->getReturnPathDomain(),
            'type' => 'CNAME',
            'value' => $postmark->getReturnPathDomainCNAMEValue(),
        ]);

        return true;
    }

    protected function site(string $domain): CustomDomain
    {
        return $this->save(
            array_merge(
                $this->alias($domain),
                [
                    'group' => Group::site(),
                ],
            ),
        );
    }

    protected function redirect(string $domain): ?CustomDomain
    {
        $hostname = null;

        if ($this->isTLD($domain)) {
            $hostname = sprintf('www.%s', $domain);
        }

        if (Str::startsWith($domain, 'www.')) {
            $hostname = Str::remove('www.', $domain);
        }

        if (!is_not_empty_string($hostname)) {
            return null;
        }

        return $this->save(
            array_merge(
                $this->alias($hostname),
                [
                    'group' => Group::redirect(),
                ],
            ),
        );
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

    protected function isTLD(string $domain): bool
    {
        $tld = app('pdp.rules')
            ->resolve($domain)
            ->registrableDomain()
            ->toString();

        return $domain === $tld;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function save(array $attributes): CustomDomain
    {
        Assert::isInstanceOf($this->tenant, Tenant::class);

        $attributes['domain'] = $this->tenant->custom_domain;

        $attributes['tenant_id'] = $this->tenant->id;

        $attributes['ok'] = true;

        return CustomDomain::create($attributes)->refresh();
    }
}
