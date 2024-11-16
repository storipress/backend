<?php

namespace App\Models\Tenants;

use App\Enums\Subscription\Type;
use App\Models\Subscriber as BaseSubscriber;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Cashier;
use Laravel\Scout\Searchable;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Typesense\LaravelTypesense\Interfaces\TypesenseDocument;
use Webmozart\Assert\Assert;

/**
 * App\Models\Tenants\Subscriber
 *
 * @property-read string $email
 * @property-read bool $bounced
 * @property-read bool $verified
 * @property-read Carbon|null $verified_at
 * @property-read string|null $first_name
 * @property-read string|null $last_name
 * @property-read string|null $pm_type
 * @property-read string|null $pm_last_four
 * @property-read string|null $avatar
 * @property-read string|null $full_name
 * @property-read string|null $name
 * @property int $id
 * @property int|null $shopify_id
 * @property string|null $hubspot_id
 * @property string|null $stripe_id
 * @property Carbon|null $trial_ends_at
 * @property bool $newsletter
 * @property Carbon|null $first_paid_at
 * @property Carbon|null $subscribed_at
 * @property \Carbon\Carbon|null $renew_on
 * @property \Illuminate\Support\Carbon|null $canceled_at
 * @property \Illuminate\Support\Carbon|null $expire_on
 * @property string $signed_up_source
 * @property string|null $paid_up_source
 * @property int $revenue
 * @property int $activity
 * @property int $active_days_last_30
 * @property int $comments_total
 * @property int $comments_last_7
 * @property int $comments_last_30
 * @property int $shares_total
 * @property int $shares_last_7
 * @property int $shares_last_30
 * @property int $email_receives
 * @property int $email_opens_total
 * @property int $email_opens_last_7
 * @property int $email_opens_last_30
 * @property int $unique_email_opens_total
 * @property int $unique_email_opens_last_7
 * @property int $unique_email_opens_last_30
 * @property int $email_link_clicks_total
 * @property int $email_link_clicks_last_7
 * @property int $email_link_clicks_last_30
 * @property int $unique_email_link_clicks_total
 * @property int $unique_email_link_clicks_last_7
 * @property int $unique_email_link_clicks_last_30
 * @property int $article_views_total
 * @property int $article_views_last_7
 * @property int $article_views_last_30
 * @property int $unique_article_views_total
 * @property int $unique_article_views_last_7
 * @property int $unique_article_views_last_30
 * @property Carbon $created_at
 * @property string $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenants\SubscriberEvent> $events
 * @property-read bool $subscribed
 * @property-read array<string, mixed>|null $subscription
 * @property-read Type $subscription_type
 * @property-read BaseSubscriber|null $parent
 * @property-read \App\Models\Tenants\AiAnalysis|null $pain_point
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Cashier\Subscription> $subscriptions
 *
 * @method static \Database\Factories\Tenants\SubscriberFactory factory($count = null, $state = [])
 * @method static Builder|Subscriber hasExpiredGenericTrial()
 * @method static Builder|Subscriber newModelQuery()
 * @method static Builder|Subscriber newQuery()
 * @method static Builder|Subscriber onGenericTrial()
 * @method static Builder|Subscriber query()
 *
 * @mixin \Eloquent
 */
class Subscriber extends Entity implements TypesenseDocument
{
    use Billable;
    use HasFactory;
    use Searchable;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'newsletter' => 'bool',
        'first_paid_at' => 'datetime',
        'subscribed_at' => 'datetime',
        'renew_on' => 'datetime',
        'canceled_at' => 'datetime',
        'expire_on' => 'datetime',
        'created_at' => 'datetime',
        'trial_ends_at' => 'datetime',
    ];

    /**
     * The relations to eager load on every query.
     *
     * @var array<int, string>
     */
    protected $with = [
        'parent',
    ];

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     */
    public function getAttribute($key): mixed
    {
        $value = parent::getAttribute($key);

        if ($value !== null) {
            return $value;
        }

        $parents = [
            'email',
            'bounced',
            'verified',
            'verified_at',
            'first_name',
            'last_name',
            'pm_type',
            'pm_last_four',
            'avatar',
            'full_name',
            'name',
        ];

        if (! in_array($key, $parents, true)) {
            return null;
        }

        return $this->parent?->getAttribute($key);
    }

    /**
     * @return BelongsTo<BaseSubscriber, Subscriber>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(BaseSubscriber::class, 'id');
    }

    /**
     * @return HasMany<SubscriberEvent>
     */
    public function events(): HasMany
    {
        return $this->hasMany(SubscriberEvent::class)
            ->latest('occurred_at');
    }

    /**
     * @return MorphOne<AiAnalysis>
     */
    public function pain_point(): MorphOne
    {
        return $this->MorphOne(
            AiAnalysis::class,
            'target',
        );
    }

    public function getRenewOnAttribute(): ?\Carbon\Carbon
    {
        if ($this->stripe() === null) {
            return null;
        }

        if (! $this->subscribed()) {
            return null;
        }

        Cashier::$calculatesTaxes = false;

        $date = $this->upcomingInvoice()?->date();

        Cashier::$calculatesTaxes = true;

        return $date;
    }

    public function getCanceledAtAttribute(): ?Carbon
    {
        if ($this->stripe() === null) {
            return null;
        }

        if (! $this->subscribed()) {
            return null;
        }

        $origin = Cashier::$customerModel;

        Cashier::$customerModel = 'App\\Models\\Tenants\\Subscriber';

        $timestamp = $this->subscription()
            ?->asStripeSubscription()
            ->canceled_at;

        $cancelledAt = $timestamp ? Carbon::createFromTimestampUTC($timestamp) : null;

        Cashier::$customerModel = $origin;

        return $cancelledAt;
    }

    public function getExpireOnAttribute(): ?Carbon
    {
        if ($this->stripe() === null) {
            return null;
        }

        if (! $this->subscribed()) {
            return null;
        }

        $origin = Cashier::$customerModel;

        Cashier::$customerModel = 'App\\Models\\Tenants\\Subscriber';

        $timestamp = $this->subscription()
            ?->asStripeSubscription()
            ->cancel_at;

        $expireOn = $timestamp ? Carbon::createFromTimestampUTC($timestamp) : null;

        Cashier::$customerModel = $origin;

        return $expireOn;
    }

    public function getSubscribedAttribute(): bool
    {
        if ($this->subscribed('manual')) {
            return true;
        }

        if ($this->stripe() === null) {
            return false;
        }

        return $this->subscribed();
    }

    public function getSubscriptionTypeAttribute(): Type
    {
        if ($this->subscribed('manual')) {
            return Type::subscribed();
        }

        if ($this->stripe() === null) {
            return Type::free();
        }

        return $this->subscribed() ? Type::subscribed() : Type::free();
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws ApiErrorException
     */
    public function getSubscriptionAttribute(): ?array
    {
        if ($this->subscribed('manual')) {
            return [
                'interval' => 'lifetime',
                'price' => '0',
            ];
        }

        $stripe = $this->stripe();

        if ($stripe === null) {
            return null;
        }

        if (! $this->hasStripeId()) {
            return null;
        }

        $subscription = $this->subscription();

        if ($subscription === null || $subscription->stripe_price === null) {
            return null;
        }

        $price = $stripe->prices->retrieve(
            $subscription->stripe_price,
        );

        return [
            'interval' => $price->recurring['interval'], // @phpstan-ignore-line
            'price' => $price->unit_amount_decimal,
        ];
    }

    /**
     * Get the Stripe SDK client.
     *
     * @param  array<mixed>  $options
     */
    public static function stripe(array $options = []): ?StripeClient
    {
        $tenant = tenant();

        if (! ($tenant instanceof Tenant)) {
            return null;
        }

        $id = $tenant->stripe_account_id;

        if (empty($id)) {
            return null;
        }

        return Cashier::stripe(array_merge($options, ['stripe_account' => $id]));
    }

    /**
     * Get the index name for the model.
     */
    public function searchableAs(): string
    {
        return tenant('id').'-'.$this->getTable();
    }

    /**
     * When updating a model, this method determines if we should update the search index.
     */
    public function searchIndexShouldBeUpdated(): bool
    {
        if ($this->wasRecentlyCreated) {
            return true;
        }

        $attributes = [
            'activity',
            'revenue',
            'subscribed_at',
        ];

        if ($this->wasChanged($attributes)) {
            return true;
        }

        Assert::isInstanceOf($this->parent, BaseSubscriber::class);

        return $this->parent->wasChanged([
            'email',
            'first_name',
            'last_name',
        ]);
    }

    /**
     * Modify the query used to retrieve models when making all of the models searchable.
     *
     * @param  Builder<Subscriber>  $query
     * @return Builder<Subscriber>
     */
    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->select(['id', 'activity', 'revenue', 'subscribed_at', 'created_at']);
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'email' => $this->email,
            'full_name' => $this->full_name,
            'activity' => $this->activity,
            'revenue' => $this->revenue,
            'subscribed_at' => $this->subscribed_at?->timestamp,
            'created_at' => $this->created_at->timestamp,
        ];
    }

    /**
     * Typesense search query by columns.
     *
     * @return string[]
     */
    public function typesenseQueryBy(): array
    {
        return [
            'email',
            'full_name',
        ];
    }

    /**
     * @return string[]
     */
    public function typesenseInfix(): array
    {
        return [
            'fallback',
            'fallback',
        ];
    }

    /**
     * Typesense search collection schema.
     *
     * @return array{
     *     name: string,
     *     default_sorting_field: string,
     *     enable_nested_fields: bool,
     *     fields: array<int, array{
     *         name: string,
     *         type: string,
     *         facet: bool,
     *         index: bool,
     *         infix: bool,
     *         sort?: bool,
     *         optional?: bool,
     *     }>,
     * }
     */
    public function getCollectionSchema(): array
    {
        return [
            'name' => $this->searchableAs(),
            'default_sorting_field' => 'email',
            'enable_nested_fields' => true,
            'fields' => [
                [
                    'name' => 'email',
                    'type' => 'string',
                    'facet' => false,
                    'index' => true,
                    'infix' => true,
                    'sort' => true,
                ],
                [
                    'name' => 'full_name',
                    'type' => 'string',
                    'facet' => false,
                    'index' => true,
                    'infix' => true,
                    'sort' => true,
                    'optional' => true,
                ],
                [
                    'name' => 'activity',
                    'type' => 'int64',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                ],
                [
                    'name' => 'revenue',
                    'type' => 'int64',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                ],
                [
                    'name' => 'subscribed_at',
                    'type' => 'int64',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                    'optional' => true,
                ],
                [
                    'name' => 'created_at',
                    'type' => 'int64',
                    'facet' => false,
                    'index' => true,
                    'infix' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function toWebhookArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'full_name' => $this->full_name,
            'activity' => $this->activity,
            'subscribed_at' => $this->subscribed_at?->timestamp,
        ];
    }
}
