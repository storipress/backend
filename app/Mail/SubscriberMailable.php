<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\Tenants\Design;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

abstract class SubscriberMailable extends Mailable
{
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function server(): string
    {
        return 'subscriptions_server_token';
    }

    /**
     * {@inheritdoc}
     */
    protected function fromStoripress(bool $usePublication = false): array
    {
        return $this->fromStoripressAlternative(true);
    }

    /**
     * {@inheritdoc}
     */
    protected function data(): array
    {
        /** @var Tenant|null $tenant */
        $tenant = Tenant::find($this->client);

        if ($tenant === null) {
            return [];
        }

        return [
            'publication' => $this->publication,
            'publication_logo' => $this->publicationLogo($tenant),
            'site_url' => $this->siteUrl(),
        ];
    }

    /**
     * Get publication logo from home design.
     */
    protected function publicationLogo(Tenant $tenant): ?string
    {
        // @phpstan-ignore-next-line
        return $tenant->run(function () use ($tenant) {
            $key = sprintf('%s-builder-logo', $tenant->id);

            return Cache::remember($key, now()->addHour(), function () {
                $design = Design::find('home', ['current'])?->current;

                if (empty($design['blocks']) || empty($design['images'])) {
                    return null;
                }

                $id = Arr::first($design['blocks']);

                if (!is_string($id)) {
                    return null;
                }

                $key = sprintf('images.b-%s.logo', $id);

                $url = Arr::get($design, $key);

                return is_string($url) ? $url : null;
            });
        });
    }
}
