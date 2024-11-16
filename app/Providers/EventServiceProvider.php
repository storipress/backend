<?php

namespace App\Providers;

use App\Events\Auth\SignedIn;
use App\Events\Auth\SignedUp;
use App\Events\Entity\Account\AccountDeleted;
use App\Events\Entity\Article\AutoPostingPathUpdated;
use App\Events\Entity as EventEntity;
use App\Events\Entity\Domain\CustomDomainCheckRequested;
use App\Events\Entity\Domain\CustomDomainEnabled;
use App\Events\Entity\Domain\CustomDomainInitialized;
use App\Events\Entity\Domain\CustomDomainRemoved;
use App\Events\Entity\Domain\WorkspaceDomainChanged;
use App\Events\Entity\Subscription\SubscriptionPlanChanged;
use App\Events\Partners as EventPartners;
use App\Events\Partners\LinkedIn\OAuthConnected as LinkedInOAuthConnected;
use App\Events\Partners\Postmark\WebhookReceived as PostmarkWebhookReceived;
use App\Events\Partners\Postmark\WebhookReceiving as PostmarkWebhookReceiving;
use App\Events\Partners\Shopify\ArticlesSynced as ShopifyArticlesSynced;
use App\Events\Partners\Shopify\ContentPulling as ShopifyContentPulling;
use App\Events\Partners\Shopify\OAuthConnected as ShopifyOAuthConnected;
use App\Events\Partners\Shopify\RedirectionsSyncing as ShopifyRedirectionsSyncing;
use App\Events\Partners\Shopify\ThemeTemplateInjecting as ShopifyThemeTemplateInjecting;
use App\Events\Partners\Shopify\WebhookReceived as ShopifyWebhookReceived;
use App\Events\WebhookPushing;
use App\Jobs\Entity\Desk\CalculateDeskArticleNumber;
use App\Listeners\Auth\EnableCustomerIoSubscription;
use App\Listeners\Entity\Account\AccountDeleted\ArchiveIntercom;
use App\Listeners\Entity\Account\AccountDeleted\ArchiveJune;
use App\Listeners\Entity\Account\AccountDeleted\ArchiveOpenReplay;
use App\Listeners\Entity\Account\AccountDeleted\DeleteOwnedTenants;
use App\Listeners\Entity\Account\AccountDeleted\RevokeAccessTokens as RevokeUserAccessTokens;
use App\Listeners\Entity\Account\AccountDeleted\RevokeJoinedTenants;
use App\Listeners\Entity\Article\AutoPostingPathUpdated\HandleShopifyArticleRedirection;
use App\Listeners\Entity as ListenerEntity;
use App\Listeners\Entity\Domain\CheckDnsRecord;
use App\Listeners\Entity\Domain\CustomDomainEnabled\EnsureBackwardCompatibility;
use App\Listeners\Entity\Domain\CustomDomainEnabled\EnsurePostmarkUpToDate;
use App\Listeners\Entity\Domain\CustomDomainEnabled\PushConfigToContentDeliveryNetwork;
use App\Listeners\Entity\Domain\CustomDomainEnabled\PushEventToRudderStack;
use App\Listeners\Entity\Domain\CustomDomainEnabled\RebuildPublicationSite as RebuildPublicationSiteOnCustomDomainEnabled;
use App\Listeners\Entity\Domain\CustomDomainRemoved\CleanupCustomDomain;
use App\Listeners\Entity\Domain\CustomDomainRemoved\CleanupPostmark;
use App\Listeners\Entity\Domain\CustomDomainRemoved\RebuildPublicationSite as RebuildPublicationSiteOnCustomDomainRemoved;
use App\Listeners\Entity\Domain\CustomDomainRemoved\RemoveCustomDomainFromContentDeliveryNetwork;
use App\Listeners\Entity\Domain\RebuildStoripressHub;
use App\Listeners\Entity\Domain\WorkspaceDomainChanged\PushConfigToCloudflare;
use App\Listeners\Entity\Domain\WorkspaceDomainChanged\RebuildPublicationSite as RebuildPublicationSiteOnWorkspaceDomainChanged;
use App\Listeners\Entity\Subscription\SubscriptionPlanChanged\AdjustAllowableEditor;
use App\Listeners\Entity\Subscription\SubscriptionPlanChanged\AdjustAllowablePublication;
use App\Listeners\Entity\Subscription\SubscriptionPlanChanged\RebuildPublications;
use App\Listeners\Entity\Subscription\SubscriptionPlanChanged\SyncPlanToPublications;
use App\Listeners\Partners as ListenerPartners;
use App\Listeners\Partners\LinkedIn;
use App\Listeners\Partners\Postmark\WebhookReceived\TransformIntoSubscriberEvent;
use App\Listeners\Partners\Postmark\WebhookReceiving\SaveEventToDatabase;
use App\Listeners\Partners\Shopify;
use App\Listeners\Partners\Zapier;
use App\Listeners\StripeWebhookHandled\HandleSubscriptionChanged;
use App\Listeners\StripeWebhookReceived\HandleInvoiceCreated;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Integration;
use App\Models\Tenants\Release;
use App\Models\Tenants\Subscriber as TenantSubscriber;
use App\Models\Tenants\User as TenantUser;
use App\Models\Tenants\UserActivity;
use App\Models\User;
use App\Observers\ArticleAutoPostingUpdatingObserver;
use App\Observers\ArticleAutoPostObserver;
use App\Observers\ArticleCorrelationObserver;
use App\Observers\ArticleNewsletterObserver;
use App\Observers\ArticleNoteNotifyingObserver;
use App\Observers\ReleaseEventsResetObserver;
use App\Observers\RudderStackSyncingObserver;
use App\Observers\TriggerSiteRebuildObserver;
use App\Observers\WebhookPushingObserver;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Cashier\Subscription;
use SocialiteProviders\Manager\SocialiteWasCalled;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<class-string, array<int, class-string|string>>
     */
    protected $listen = [
        // --- entity ---

        EventEntity\Account\AvatarRemoved::class => [
            ListenerEntity\Account\AvatarRemoved\RecordUserAction::class,
        ],

        EventEntity\Article\ArticleCreated::class => [
            CalculateDeskArticleNumber::class,
            ListenerEntity\Article\ArticleCreated\CreateWebflowArticleItem::class,
            ListenerEntity\Article\ArticleCreated\CreateWordpressPost::class,
        ],

        EventEntity\Article\ArticleDeleted::class => [
            CalculateDeskArticleNumber::class,
            ListenerEntity\Article\ArticleDeleted\ReleaseSlug::class,
            ListenerEntity\Article\ArticleDeleted\ArchivedWebflowArticleItem::class,
            ListenerEntity\Article\ArticleDeleted\DeleteWordPressPost::class,
        ],

        EventEntity\Article\ArticleDeskChanged::class => [
            CalculateDeskArticleNumber::class,
            ListenerEntity\Article\ArticleDeskChanged\UpdateWebflowArticleItem::class,
        ],

        EventEntity\Article\ArticleDuplicated::class => [
            CalculateDeskArticleNumber::class,
            ListenerEntity\Article\ArticleDuplicated\CreateWebflowArticleItem::class,
            ListenerEntity\Article\ArticleDuplicated\CreateWordpressPost::class,
        ],

        EventEntity\Article\ArticleLived::class => [
            //
        ],

        EventEntity\Article\ArticlePublished::class => [
            CalculateDeskArticleNumber::class,
            ListenerEntity\Article\ArticlePublished\PublishWebflowArticleItem::class,
            ListenerEntity\Article\ArticlePublished\UpdateWebflowAuthorItem::class,
            ListenerEntity\Article\ArticlePublished\UpdateWebflowDeskItem::class,
            ListenerEntity\Article\ArticlePublished\UpdateShopifyArticleDistribution::class,
            ListenerEntity\Article\ArticlePublished\UpdateWordpressPost::class,
        ],

        EventEntity\Article\ArticleRestored::class => [
            CalculateDeskArticleNumber::class,
            ListenerEntity\Article\ArticleRestored\DraftWebflowArticleItem::class,
        ],

        EventEntity\Article\ArticleUnpublished::class => [
            CalculateDeskArticleNumber::class,
            ListenerEntity\Article\ArticleUnpublished\DraftWebflowArticleItem::class,
            ListenerEntity\Article\ArticleUnpublished\UpdateWebflowAuthorItem::class,
            ListenerEntity\Article\ArticleUnpublished\UpdateWebflowDeskItem::class,
            ListenerEntity\Article\ArticleUnpublished\UpdateWordpressPost::class,
        ],

        EventEntity\Article\ArticleUpdated::class => [
            ListenerEntity\Article\ArticleUpdated\UpdateWebflowArticleItem::class,
            ListenerEntity\Article\ArticleUpdated\UpdateWordpressPost::class,
        ],

        EventEntity\Block\BlockUpdated::class => [
            ListenerEntity\Block\BlockUpdated\TriggerSiteBuild::class,
        ],

        EventEntity\Block\BlockDeleted::class => [
            ListenerEntity\Block\BlockDeleted\TriggerSiteBuild::class,
        ],

        EventEntity\CustomField\CustomFieldValueCreated::class => [
            ListenerEntity\CustomField\CustomFieldValueCreated\UpdateWebflowArticleItem::class,
        ],

        EventEntity\CustomField\CustomFieldValueUpdated::class => [
            ListenerEntity\CustomField\CustomFieldValueUpdated\UpdateWebflowArticleItem::class,
        ],

        EventEntity\Design\DesignUpdated::class => [
            ListenerEntity\Design\DesignUpdated\RecordUserAction::class,
            ListenerEntity\Design\DesignUpdated\TriggerSiteBuild::class,
        ],

        EventEntity\Desk\DeskCreated::class => [
            ListenerEntity\Desk\DeskCreated\CreateWebflowDeskItem::class,
            ListenerEntity\Desk\DeskCreated\CreateWordPressCategory::class,
            ListenerEntity\Desk\DeskCreated\RelocateParentArticle::class,
            ListenerEntity\Desk\DeskCreated\RecordUserAction::class,
        ],

        EventEntity\Desk\DeskDeleted::class => [
            ListenerEntity\Desk\DeskDeleted\DeleteWebflowDeskItem::class,
            ListenerEntity\Desk\DeskDeleted\DeleteWordPressCategory::class,
            ListenerEntity\Desk\DeskDeleted\ReleaseSlug::class,
            ListenerEntity\Desk\DeskDeleted\RelocateArticle::class,
            ListenerEntity\Desk\DeskDeleted\CleanupRelation::class,
            ListenerEntity\Desk\DeskDeleted\RecordUserAction::class,
            ListenerEntity\Desk\DeskDeleted\TriggerSiteBuild::class,
        ],

        EventEntity\Desk\DeskHierarchyChanged::class => [
            ListenerEntity\Desk\DeskHierarchyChanged\RecordUserAction::class,
            ListenerEntity\Desk\DeskHierarchyChanged\TriggerSiteBuild::class,
        ],

        EventEntity\Desk\DeskOrderChanged::class => [
            ListenerEntity\Desk\DeskOrderChanged\RecordUserAction::class,
            ListenerEntity\Desk\DeskOrderChanged\TriggerSiteBuild::class,
        ],

        EventEntity\Desk\DeskUpdated::class => [
            ListenerEntity\Desk\DeskUpdated\UpdateWebflowDeskItem::class,
            ListenerEntity\Desk\DeskUpdated\UpdateWordPressCategory::class,
            ListenerEntity\Desk\DeskUpdated\UpdateShopifyDeskRedirection::class,
            ListenerEntity\Desk\DeskUpdated\RecordUserAction::class,
            ListenerEntity\Desk\DeskUpdated\TriggerScoutSync::class,
            ListenerEntity\Desk\DeskUpdated\TriggerSiteBuild::class,
        ],

        EventEntity\Desk\DeskUserAdded::class => [
            ListenerEntity\Desk\DeskUserAdded\UpdateWebflowDeskItem::class,
        ],

        EventEntity\Desk\DeskUserRemoved::class => [
            ListenerEntity\Desk\DeskUserRemoved\UpdateWebflowDeskItem::class,
        ],

        EventEntity\Integration\IntegrationActivated::class => [
            ListenerEntity\Integration\IntegrationActivated\TriggerSiteBuild::class,
        ],

        EventEntity\Integration\IntegrationConfigurationUpdated::class => [
            ListenerEntity\Integration\IntegrationConfigurationUpdated\DetectWebflowOnboarded::class,
        ],

        EventEntity\Integration\IntegrationDeactivated::class => [
            ListenerEntity\Integration\IntegrationDeactivated\TriggerSiteBuild::class,
        ],

        EventEntity\Integration\IntegrationDisconnected::class => [
            ListenerEntity\Integration\IntegrationDisconnected\TriggerSiteBuild::class,
        ],

        EventEntity\Integration\IntegrationUpdated::class => [
            ListenerEntity\Integration\IntegrationUpdated\UpdateWebflowDomain::class,
            ListenerEntity\Integration\IntegrationUpdated\UpdateShopifyPrefix::class,
            ListenerEntity\Integration\IntegrationUpdated\TriggerSiteBuild::class,
        ],

        EventEntity\Layout\LayoutCreated::class => [
            ListenerEntity\Layout\LayoutCreated\RecordUserAction::class,
        ],

        EventEntity\Layout\LayoutDeleted::class => [
            ListenerEntity\Layout\LayoutDeleted\SetInUsedToNull::class,
            ListenerEntity\Layout\LayoutDeleted\RecordUserAction::class,
            ListenerEntity\Layout\LayoutDeleted\TriggerSiteBuild::class,
        ],

        EventEntity\Layout\LayoutUpdated::class => [
            ListenerEntity\Layout\LayoutUpdated\RecordUserAction::class,
            ListenerEntity\Layout\LayoutUpdated\TriggerSiteBuild::class,
        ],

        EventEntity\Page\PageCreated::class => [
            ListenerEntity\Page\PageCreated\RecordUserAction::class,
        ],

        EventEntity\Page\PageDeleted::class => [
            ListenerEntity\Page\PageDeleted\RecordUserAction::class,
            ListenerEntity\Page\PageDeleted\TriggerSiteBuild::class,
        ],

        EventEntity\Page\PageUpdated::class => [
            ListenerEntity\Page\PageUpdated\RecordUserAction::class,
            ListenerEntity\Page\PageUpdated\TriggerSiteBuild::class,
        ],

        EventEntity\Subscriber\SubscriberActivityRecorded::class => [
            ListenerEntity\Subscriber\SubscriberActivityRecorded\AnalyzeSubscriberPainPoints::class,
        ],

        EventEntity\Tag\TagCreated::class => [
            ListenerEntity\Tag\TagCreated\CreateWebflowTagItem::class,
            ListenerEntity\Tag\TagCreated\CreateWordPressTag::class,
            ListenerEntity\Tag\TagCreated\RecordUserAction::class,
        ],

        EventEntity\Tag\TagDeleted::class => [
            ListenerEntity\Tag\TagDeleted\DeleteWebflowTagItem::class,
            ListenerEntity\Tag\TagDeleted\DeleteWordPressTag::class,
            ListenerEntity\Tag\TagDeleted\RecordUserAction::class,
            ListenerEntity\Tag\TagDeleted\TriggerSiteBuild::class,
        ],

        EventEntity\Tag\TagUpdated::class => [
            ListenerEntity\Tag\TagUpdated\UpdateWebflowTagItem::class,
            ListenerEntity\Tag\TagUpdated\UpdateWordPressTag::class,
            ListenerEntity\Tag\TagUpdated\RecordUserAction::class,
            ListenerEntity\Tag\TagUpdated\TriggerSiteBuild::class,
        ],

        EventEntity\Tenant\TenantDeleted::class => [
            ListenerEntity\Tenant\TenantDeleted\RevokeOAuthTokens::class,
            ListenerEntity\Tenant\TenantDeleted\RevokeAccessTokens::class,
            ListenerEntity\Tenant\TenantDeleted\CleanupAssets::class,
            ListenerEntity\Tenant\TenantDeleted\CleanupDomain::class,
            ListenerEntity\Tenant\TenantDeleted\CleanupTypesense::class,
            ListenerEntity\Tenant\TenantDeleted\CleanupCloudflarePage::class,
            ListenerEntity\Tenant\TenantDeleted\CleanupContentDeliveryNetwork::class,
        ],

        EventEntity\Tenant\TenantUpdated::class => [
            ListenerEntity\Tenant\TenantUpdated\TriggerSiteBuild::class,
            ListenerEntity\Tenant\TenantUpdated\UpdateWordPressSiteInfo::class,
        ],

        EventEntity\Tenant\UserRoleChanged::class => [
            ListenerEntity\Tenant\UserRoleChanged\UpdateWebflowAuthorItem::class,
            ListenerEntity\Tenant\UserRoleChanged\UpdateWebflowDeskItem::class,
        ],

        EventEntity\Tenant\UserJoined::class => [
            ListenerEntity\Tenant\UserJoined\CreateWebflowAuthorItem::class,
            ListenerEntity\Tenant\UserJoined\CreateWordPressUser::class,
        ],

        EventEntity\Tenant\UserLeaved::class => [
            ListenerEntity\Tenant\UserLeaved\DeleteWebflowAuthorItem::class,
            ListenerEntity\Tenant\UserLeaved\DeleteWordPressUser::class,
        ],

        EventEntity\User\UserUpdated::class => [
            ListenerEntity\User\UserUpdated\UpdateWebflowAuthorItem::class,
            ListenerEntity\User\UserUpdated\UpdateWordPressUser::class,
            ListenerEntity\User\UserUpdated\RecordUserAction::class,
            ListenerEntity\User\UserUpdated\TriggerScoutSync::class,
            ListenerEntity\User\UserUpdated\TriggerSiteBuild::class,
        ],

        // --- partners ---

        EventPartners\Revert\HubSpotOAuthConnected::class => [
            ListenerPartners\Revert\HubSpotOAuthConnected\SetupProperty::class,
            ListenerPartners\Revert\HubSpotOAuthConnected\RecordEvent::class,
        ],

        EventPartners\Webflow\CollectionConnected::class => [
            ListenerPartners\Webflow\CollectionConnected\MapCollectionFields::class,
            ListenerPartners\Webflow\CollectionConnected\RecordEvent::class,
        ],

        EventPartners\Webflow\CollectionCreating::class => [
            ListenerPartners\Webflow\CollectionCreating\CreateBlogCollection::class,
            ListenerPartners\Webflow\CollectionCreating\CreateAuthorCollection::class,
            ListenerPartners\Webflow\CollectionCreating\CreateDeskCollection::class,
            ListenerPartners\Webflow\CollectionCreating\CreateTagCollection::class,
            ListenerPartners\Webflow\CollectionCreating\RecordEvent::class,
        ],

        EventPartners\Webflow\CollectionSchemaOutdated::class => [
            ListenerPartners\Webflow\CollectionSchemaOutdated\PullCollectionSchema::class,
            ListenerPartners\Webflow\CollectionSchemaOutdated\RecordEvent::class,
        ],

        EventPartners\Webflow\OAuthConnected::class => [
            ListenerPartners\Webflow\OAuthConnected\SetupIntegration::class,
            ListenerPartners\Webflow\OAuthConnected\DetectMainSite::class,
            ListenerPartners\Webflow\OAuthConnected\DetectCollection::class,
            ListenerPartners\Webflow\OAuthConnected\SetupPublication::class,
            ListenerPartners\Webflow\OAuthConnected\SetupCodeInjection::class,
            ListenerPartners\Webflow\OAuthConnected\RecordUserAction::class,
            ListenerPartners\Webflow\OAuthConnected\RecordEvent::class,
        ],

        EventPartners\Webflow\OAuthConnecting::class => [
            ListenerPartners\Webflow\OAuthConnecting\RecordEvent::class,
        ],

        EventPartners\Webflow\OAuthDisconnected::class => [
            ListenerPartners\Webflow\OAuthDisconnected\CleanupIntegration::class,
            ListenerPartners\Webflow\OAuthDisconnected\CleanupTenant::class,
            ListenerPartners\Webflow\OAuthDisconnected\CleanupWebflowId::class,
            ListenerPartners\Webflow\OAuthDisconnected\RemoveCodeInjection::class,
            ListenerPartners\Webflow\OAuthDisconnected\RecordUserAction::class,
            ListenerPartners\Webflow\OAuthDisconnected\TriggerSiteBuild::class,
            ListenerPartners\Webflow\OAuthDisconnected\RecordEvent::class,
        ],

        EventPartners\Webflow\Onboarded::class => [
            ListenerPartners\Webflow\Onboarded\SyncContent::class,
            ListenerPartners\Webflow\Onboarded\SetupWebhooks::class,
            ListenerPartners\Webflow\Onboarded\RecordEvent::class,
        ],

        EventPartners\Webflow\WebhookReceived::class => [
            //
        ],

        EventPartners\Webflow\Webhooks\CollectionItemCreated::class => [
            ListenerPartners\Webflow\Webhooks\CollectionItemCreated\HandleItemCreated::class,
        ],

        EventPartners\Webflow\Webhooks\CollectionItemChanged::class => [
            ListenerPartners\Webflow\Webhooks\CollectionItemChanged\HandleItemChanged::class,
        ],

        EventPartners\Webflow\Webhooks\CollectionItemDeleted::class => [
            ListenerPartners\Webflow\Webhooks\CollectionItemDeleted\HandleItemDeleted::class,
        ],

        EventPartners\Webflow\Webhooks\CollectionItemUnpublished::class => [
            ListenerPartners\Webflow\Webhooks\CollectionItemUnpublished\HandleItemUnpublished::class,
        ],

        EventPartners\WordPress\Connected::class => [
            ListenerPartners\WordPress\Connected\SetupIntegration::class,
            ListenerPartners\WordPress\Connected\SetupPublication::class,
            ListenerPartners\WordPress\Connected\SyncContent::class,
        ],

        EventPartners\WordPress\Disconnected::class => [
            ListenerPartners\WordPress\Disconnected\CleanupIntegration::class,
            ListenerPartners\WordPress\Disconnected\CleanupTenant::class,
            ListenerPartners\WordPress\Disconnected\CleanupWordPressId::class,
        ],

        EventPartners\WordPress\Webhooks\PostSaved::class => [
            ListenerPartners\WordPress\Webhooks\PostSaved\PullPostFromWordPress::class,
        ],

        EventPartners\WordPress\Webhooks\PostDeleted::class => [
            ListenerPartners\WordPress\Webhooks\PostDeleted\DeletePost::class,
        ],

        EventPartners\WordPress\Webhooks\TagCreated::class => [
            ListenerPartners\WordPress\Webhooks\TagCreated\PullTagFromWordPress::class,
        ],

        EventPartners\WordPress\Webhooks\TagEdited::class => [
            ListenerPartners\WordPress\Webhooks\TagEdited\PullTagFromWordPress::class,
        ],

        EventPartners\WordPress\Webhooks\TagDeleted::class => [
            ListenerPartners\WordPress\Webhooks\TagDeleted\DeleteTag::class,
        ],

        EventPartners\WordPress\Webhooks\CategoryCreated::class => [
            ListenerPartners\WordPress\Webhooks\CategoryCreated\PullCategoryFromWordPress::class,
        ],

        EventPartners\WordPress\Webhooks\CategoryEdited::class => [
            ListenerPartners\WordPress\Webhooks\CategoryEdited\PullCategoryFromWordPress::class,
        ],

        EventPartners\WordPress\Webhooks\CategoryDeleted::class => [
            ListenerPartners\WordPress\Webhooks\CategoryDeleted\DeleteDesk::class,
        ],

        EventPartners\WordPress\Webhooks\UserCreated::class => [
            ListenerPartners\WordPress\Webhooks\UserCreated\PullUserFromWordPress::class,
        ],

        EventPartners\WordPress\Webhooks\UserEdited::class => [
            ListenerPartners\WordPress\Webhooks\UserEdited\PullUserFromWordPress::class,
        ],

        EventPartners\WordPress\Webhooks\UserDeleted::class => [
            ListenerPartners\WordPress\Webhooks\UserDeleted\DeleteUser::class,
        ],

        EventPartners\WordPress\Webhooks\PluginUpgraded::class => [
            ListenerPartners\WordPress\Webhooks\PluginUpgraded\UpdateIntegration::class,
            ListenerPartners\WordPress\Webhooks\PluginUpgraded\UpdatePublication::class,
        ],

        // --- old ---

        SignedUp::class => [
            EnableCustomerIoSubscription::class,
        ],

        SignedIn::class => [
            EnableCustomerIoSubscription::class,
        ],

        AccountDeleted::class => [
            RevokeUserAccessTokens::class,
            RevokeJoinedTenants::class,
            DeleteOwnedTenants::class,
            ArchiveIntercom::class,
            ArchiveJune::class,
            ArchiveOpenReplay::class,
        ],

        WorkspaceDomainChanged::class => [
            PushConfigToCloudflare::class,
            RebuildPublicationSiteOnWorkspaceDomainChanged::class,
        ],

        CustomDomainCheckRequested::class => [
            CheckDnsRecord::class,
        ],

        CustomDomainInitialized::class => [
            CheckDnsRecord::class,
        ],

        CustomDomainEnabled::class => [
            RebuildStoripressHub::class,
            RebuildPublicationSiteOnCustomDomainEnabled::class,
            PushConfigToContentDeliveryNetwork::class,
            PushEventToRudderStack::class,
            EnsureBackwardCompatibility::class,
            EnsurePostmarkUpToDate::class,
        ],

        CustomDomainRemoved::class => [
            RebuildStoripressHub::class,
            RebuildPublicationSiteOnCustomDomainRemoved::class,
            RemoveCustomDomainFromContentDeliveryNetwork::class,
            CleanupCustomDomain::class,
            CleanupPostmark::class,
        ],

        SubscriptionPlanChanged::class => [
            AdjustAllowableEditor::class,
            AdjustAllowablePublication::class,
            RebuildPublications::class,
            SyncPlanToPublications::class,
        ],

        LinkedInOAuthConnected::class => [
            LinkedIn\OAuthConnected\SetupIntegration::class,
            LinkedIn\OAuthConnected\SetupPublication::class,
        ],

        WebhookReceived::class => [
            HandleInvoiceCreated::class,
        ],

        WebhookHandled::class => [
            HandleSubscriptionChanged::class,
        ],

        SocialiteWasCalled::class => [
            // add your listeners (aka providers) here
            'SocialiteProviders\\LinkedIn\\LinkedInExtendSocialite@handle',
            'SocialiteProviders\\Slack\\SlackExtendSocialite@handle',
            'SocialiteProviders\\Shopify\\ShopifyExtendSocialite@handle',
        ],

        PostmarkWebhookReceiving::class => [
            SaveEventToDatabase::class,
        ],

        PostmarkWebhookReceived::class => [
            TransformIntoSubscriberEvent::class,
        ],

        ShopifyOAuthConnected::class => [
            Shopify\OAuthConnected\SetupWebhookSubscription::class,
            Shopify\OAuthConnected\SetupAppProxy::class,
            Shopify\OAuthConnected\SetupIntegration::class,
            Shopify\OAuthConnected\SetupPublication::class,
            Shopify\OAuthConnected\SetupArticleDistributions::class,
            Shopify\OAuthConnected\PushEventToCustomerDataPlatform::class,
            Shopify\OAuthConnected\InjectThemeTemplate::class,
        ],

        ShopifyWebhookReceived::class => [
            Shopify\WebhookReceived\HandleAppUninstalled::class,
            Shopify\WebhookReceived\HandleCustomersCreate::class,
            Shopify\WebhookReceived\HandleCustomersDelete::class,
            Shopify\WebhookReceived\HandleCustomersUpdate::class,
            Shopify\WebhookReceived\HandleCustomersDataRequest::class,
            Shopify\WebhookReceived\HandleCustomersRedact::class,
            Shopify\HandleThemeTemplateInjection::class,
            Shopify\WebhookReceived\HandleUnknown::class,
        ],

        ShopifyContentPulling::class => [
            Shopify\ContentPulling\SyncBlogArticles::class,
            Shopify\ContentPulling\SyncTags::class,
        ],

        ShopifyRedirectionsSyncing::class => [
            Shopify\HandleRedirections::class,
        ],

        ShopifyThemeTemplateInjecting::class => [
            Shopify\HandleThemeTemplateInjection::class,
        ],

        AutoPostingPathUpdated::class => [
            Shopify\HandleRedirections::class,
            HandleShopifyArticleRedirection::class,
        ],

        ShopifyArticlesSynced::class => [
            Shopify\HandleRedirections::class,
        ],

        WebhookPushing::class => [
            Zapier\WebhookPush\PushArticleCreated::class,
            Zapier\WebhookPush\PushArticleDeleted::class,
            Zapier\WebhookPush\PushArticleUpdated::class,
            Zapier\WebhookPush\PushArticleNewsletterSent::class,
            Zapier\WebhookPush\PushArticlePublished::class,
            Zapier\WebhookPush\PushArticleStageChanged::class,
            Zapier\WebhookPush\PushArticleUnpublished::class,
            Zapier\WebhookPush\PushSubscriberCreated::class,
        ],
    ];

    /**
     * The model observers to register.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $observers = [
        Article::class => [
            ArticleAutoPostingUpdatingObserver::class,
            ArticleCorrelationObserver::class,
            TriggerSiteRebuildObserver::class,
            WebhookPushingObserver::class,
        ],

        Integration::class => [
            RudderStackSyncingObserver::class,
        ],

        Subscription::class => [
            RudderStackSyncingObserver::class,
        ],

        Tenant::class => [
            RudderStackSyncingObserver::class,
        ],

        TenantSubscriber::class => [
            WebhookPushingObserver::class,
        ],

        TenantUser::class => [
            RudderStackSyncingObserver::class,
        ],

        User::class => [
            RudderStackSyncingObserver::class,
        ],

        UserActivity::class => [
            ArticleNoteNotifyingObserver::class,
        ],

        Release::class => [
            ArticleAutoPostObserver::class,
            ArticleNewsletterObserver::class,
            ArticleNoteNotifyingObserver::class,
            ReleaseEventsResetObserver::class,
            WebhookPushingObserver::class,
        ],
    ];
}
