<?php

namespace App\Models;

use App\Authentication\Authenticatable;
use App\Enums\CustomField\GroupType;
use App\Enums\Site\Generator;
use App\Enums\Site\Hosting;
use App\Enums\Tenant\State;
use App\Models\Attributes\HasCustomFields;
use App\Models\Tenants\Article;
use App\Models\Tenants\CustomField;
use App\Models\Tenants\Integrations\Webflow;
use App\Models\Tenants\User as TenantUser;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Support\Str;
use JsonException;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\DatabaseConfig;
use Typesense\LaravelTypesense\Typesense;
use Webmozart\Assert\Assert;

/**
 * App\Models\Tenant
 *
 * @property array{
 *     user_id: string,
 *     access_token: string,
 * }|null $facebook_data
 * @property array{
 *     user_id: string,
 *     expires_on: int,
 *     access_token: string,
 *     refresh_token: string,
 * }|null $twitter_data
 * @property array<string, mixed>|null $shopify_data
 * @property array<string, string>|null $slack_data
 * @property array<string, mixed>|null $webflow_data
 * @property array<string, mixed>|null $wordpress_data
 * @property array<string, string>|null $linkedin_data
 * @property string|null $custom_site_template_path
 * @property int|null $postmark_id
 * @property string|null $site_domain
 * @property string|null $mail_domain
 * @property array|null $permalinks
 * @property array|null $sitemap
 * @property Hosting|null $hosting
 * @property array|null $desk_alias
 * @property array|null $buildx
 * @property array|null $paywall_config
 * @property array{
 *     company?: string,
 *     core_competency?: string,
 *     days_on_hold?: int,
 *     email?: array{
 *         sign_off?: string,
 *         bcc?: string,
 *         unsubscribe_link?: bool,
 *     },
 * }|null $prophet_config
 * @property-read array<int, string>|null $invites
 * @property-read string|null $stripe_account_id
 * @property-read string|null $stripe_product_id
 * @property-read string|null $stripe_monthly_price_id
 * @property-read string|null $stripe_yearly_price_id
 * @property-read string|null $cloudflare_health_check_id
 * @property-read mixed[] $postmark
 * @property-read UserStatus $tenant_user_pivot
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property string|null $email
 * @property string $timezone
 * @property mixed|null $favicon
 * @property array|null $socials
 * @property bool $initialized
 * @property string $workspace
 * @property string|null $custom_domain
 * @property int|null $cloudflare_page_id
 * @property string $wss_secret
 * @property string|null $tenancy_db_name
 * @property string|null $tenancy_db_username
 * @property string|null $tenancy_db_password
 * @property array|null $data
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property bool $newsletter
 * @property bool $subscription
 * @property string|null $accent_color
 * @property string|null $currency
 * @property string|null $monthly_price
 * @property string|null $yearly_price
 * @property int $subscription_setup
 * @property array|null $tutorials
 * @property bool $subscription_setup_done
 * @property-read \App\Models\AccessToken|null $accessToken
 * @property-read \App\Models\CloudflarePage|null $cloudflare_page
 * @property-read Collection<int, \App\Models\CloudflarePageDeployment> $cloudflare_page_deployments
 * @property-read Collection<int, \App\Models\CustomDomain> $custom_domains
 * @property-read string $cf_pages_domain
 * @property-read string $cf_pages_url
 * @property bool $custom_site_template
 * @property-read string $customer_site_storipress_url
 * @property-read bool $enabled
 * @property-read string $generator
 * @property-read bool $is_ssg
 * @property-read bool $is_ssr
 * @property-read string $lang
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CustomField> $metafields
 * @property-read string|null $newstand_key
 * @property-read string $plan
 * @property-read bool $has_prophet
 * @property-read string $site_storipress_domain
 * @property-read string $state
 * @property-read string $typesense_search_only_key
 * @property-read string $url
 * @property-read \App\Models\Image|null $logo
 * @property-read \App\Models\Media|null $logo_v2
 * @property-read \App\Models\User $owner
 * @property-read Collection<int, \App\Models\Subscriber> $subscribers
 * @property-read Collection<int, \App\Models\User> $users
 *
 * @method static \Database\Factories\TenantFactory factory($count = null, $state = [])
 * @method static Builder|Tenant initialized()
 * @method static Builder|Tenant newModelQuery()
 * @method static Builder|Tenant newQuery()
 * @method static Builder|Tenant onlyTrashed()
 * @method static Builder|Tenant query()
 * @method static Builder|Tenant withTrashed()
 * @method static Builder|Tenant withoutTrashed()
 *
 * @property-read array<int, array<string, string>> $custom_domain_email
 *
 * @method static \Stancl\Tenancy\Database\TenantCollection<int, static> all($columns = ['*'])
 * @method static \Stancl\Tenancy\Database\TenantCollection<int, static> get($columns = ['*'])
 *
 * @mixin \Eloquent
 */
class Tenant extends BaseTenant implements AuthenticatableContract, AuthorizableContract, TenantWithDatabase
{
    use Authenticatable;
    use Authorizable;
    use HasCustomFields;
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'initialized' => 'bool',
        'socials' => 'array',
        'newsletter' => 'bool',
        'subscription' => 'bool',
        'subscription_setup_done' => 'bool',
        'hosting' => Hosting::class,
        'custom_site_template' => 'bool',
    ];

    public function database(): DatabaseConfig
    {
        $tenant = clone $this;

        $tenant->tenancy_db_username = config('database.connections.mysql.username'); // @phpstan-ignore-line

        $tenant->tenancy_db_password = config('database.connections.mysql.password'); // @phpstan-ignore-line

        return new DatabaseConfig($tenant);
    }

    /**
     * @return BelongsTo<User, Tenant>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id',
        );
    }

    /**
     * @return MorphOne<Image>
     */
    public function logo(): MorphOne
    {
        return $this->morphOne(
            Image::class,
            'imageable',
        );
    }

    /**
     * @return MorphOne<Media>
     */
    public function logo_v2(): MorphOne
    {
        return $this->morphOne(Media::class, 'model')
            ->where('collection', '=', 'publication-logo')
            ->latest();
    }

    /**
     * @return BelongsToMany<User>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->as('tenant_user_pivot')
            ->using(UserStatus::class)
            ->withPivot('id', 'status', 'hidden', 'role');
    }

    /**
     * @return BelongsToMany<Subscriber>
     */
    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(Subscriber::class)
            ->as('subscriber_tenant_pivot')
            ->withPivot('id')
            ->withTimestamps();
    }

    /**
     * @return HasMany<CustomDomain>
     */
    public function custom_domains(): HasMany
    {
        return $this->hasMany(CustomDomain::class);
    }

    /**
     * @return MorphOne<AccessToken>
     */
    public function accessToken(): MorphOne
    {
        return $this->morphOne(AccessToken::class, 'tokenable')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at');
    }

    /**
     * Scope a query to only include initialized tenants.
     *
     * @param  Builder<Tenant>  $query
     * @return Builder<Tenant>
     */
    public function scopeInitialized(Builder $query): Builder
    {
        return $query->where('initialized', '=', true);
    }

    public function getNewstandKeyAttribute(): ?string
    {
        $auth = auth()->user();

        if (! ($auth instanceof User)) {
            return null;
        }

        $user = TenantUser::find($auth->id);

        if ($user === null) {
            return null;
        }

        if (! in_array($user->role, ['owner', 'admin'], true)) {
            return null;
        }

        return $this->accessToken?->token;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CustomField>
     */
    public function getMetafieldsAttribute(): Collection
    {
        return $this->getCustomFields(GroupType::publicationMetafield());
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getCustomDomainEmailAttribute(): array
    {
        $postmark = $this->postmark;

        if (! is_array($postmark) || empty($postmark)) {
            return [];
        }

        return [
            [
                'hostname' => $postmark['dkimpendinghost'] ?: $postmark['dkimhost'],
                'type' => 'TXT',
                'value' => $postmark['dkimpendingtextvalue'] ?: $postmark['dkimtextvalue'],
            ],
            [
                'hostname' => $postmark['returnpathdomain'] ?: sprintf('pm-bounces.%s', $postmark['name']),
                'type' => 'CNAME',
                'value' => $postmark['returnpathdomaincnamevalue'],
            ],
        ];
    }

    public function getEnabledAttribute(): bool
    {
        return (bool) ($this->attributes['enabled'] ?? true);
    }

    public function getLangAttribute(): string
    {
        return $this->attributes['lang'] ?? 'en-US';
    }

    public function getHostingAttribute(): string
    {
        return $this->attributes['hosting'] ?? Hosting::storipress;
    }

    public function getStateAttribute(): string
    {
        if (! empty($this->attributes['deleted_at'])) {
            return State::deleted();
        }

        if (empty($this->attributes['initialized'])) {
            return State::uninitialized();
        }

        return State::online();
    }

    /**
     * @throws JsonException
     */
    public function getTypesenseSearchOnlyKeyAttribute(): string
    {
        $key = config('scout.typesense.search_only_key');

        Assert::stringNotEmpty($key);

        return app(Typesense::class)
            ->getClient()
            ->getKeys()
            ->generateScopedSearchKey(
                $key,
                [
                    'collection' => (new Article())->searchableAs(),
                    'filter_by' => 'published:=true',
                    'expires_at' => now()->addYears(5)->timestamp,
                ],
            );
    }

    public function getPlanAttribute(): string
    {
        return ($this->attributes['plan'] ?? '') ?: 'free';
    }

    public function getHasProphetAttribute(): bool
    {
        return $this->owner->subscriptions()->where('stripe_price', '=', 'prophet')->exists();
    }

    public function getGeneratorAttribute(): string
    {
        return ($this->attributes['generator'] ?? '') ?: Generator::v2;
    }

    public function getIsSsgAttribute(): bool
    {
        return $this->generator === Generator::v1;
    }

    public function getIsSsrAttribute(): bool
    {
        return $this->generator !== Generator::v1;
    }

    public function getUrlAttribute(): string
    {
        if (isset($this->wordpress_data['url']) && is_not_empty_string($this->wordpress_data['url'])) {
            return rtrim(Str::after($this->wordpress_data['url'], '://'), '/');
        }

        if (isset($this->webflow_data['site_id'])) {
            $domain = $this->run(fn () => Webflow::retrieve()->config->domain);

            if (is_not_empty_string($domain)) {
                return $domain;
            }
        }

        if (! empty($this->custom_domain)) {
            return Str::lower($this->custom_domain);
        }

        return $this->customer_site_storipress_url;
    }

    public function getSiteStoripressDomainAttribute(): string
    {
        return $this->customer_site_storipress_url;
    }

    public function getCustomerSiteStoripressUrlAttribute(): string
    {
        $env = app()->environment();

        $workspace = Str::lower($this->workspace);

        if ($env === 'production') {
            return $workspace.'.storipress.app';
        }

        if ($env === 'staging') {
            return $workspace.'-cdn.storipress.pro';
        }

        return $workspace.'-cdn.storipress.dev';
    }

    public function setCustomSiteTemplateAttribute(mixed $value): void
    {
        $this->attributes['custom_site_template'] = (bool) $value;
    }

    public function getCustomSiteTemplateAttribute(): bool
    {
        return (bool) ($this->attributes['custom_site_template'] ?? false);
    }

    /**
     * Custom tenant columns.
     *
     * Attributes of the tenant model which don't
     * have their own column will be stored in
     * the data JSON column.
     *
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'user_id',
            'name',
            'description',
            'email',
            'timezone',
            'favicon',
            'socials',
            'facebook',
            'twitter',
            'initialized',
            'workspace',
            'custom_domain',
            'cloudflare_page_id',
            'wss_secret',
            'newsletter',
            'subscription',
            'accent_color',
            'currency',
            'monthly_price',
            'yearly_price',
            'tenancy_db_name',
            'tenancy_db_username',
            'tenancy_db_password',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    /* Relations */

    /**
     * @return BelongsTo<CloudflarePage, Tenant>
     */
    public function cloudflare_page(): belongsTo
    {
        return $this->belongsTo(CloudflarePage::class);
    }

    /**
     * @return HasMany<CloudflarePageDeployment>
     */
    public function cloudflare_page_deployments(): HasMany
    {
        return $this->hasMany(CloudflarePageDeployment::class);
    }

    /* Attributes */

    public function getCfPagesDomainAttribute(): string
    {
        Assert::isInstanceOf($this->cloudflare_page, CloudflarePage::class);

        return sprintf(
            '%s.%s.pages.dev',
            Str::lower($this->id),
            $this->cloudflare_page->name,
        );
    }

    public function getCfPagesUrlAttribute(): string
    {
        return sprintf('https://%s', $this->cf_pages_domain);
    }

    /* Others */
}
