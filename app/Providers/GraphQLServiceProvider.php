<?php

namespace App\Providers;

use App\Enums\Article;
use App\Enums\AutoPosting;
use App\Enums\Credit;
use App\Enums\CustomDomain;
use App\Enums\CustomField;
use App\Enums\Link;
use App\Enums\Release;
use App\Enums\Scraper;
use App\Enums\Site;
use App\Enums\Subscription;
use App\Enums\Template;
use App\Enums\Upload;
use App\Enums\User;
use App\Enums\Webflow;
use App\Enums\WordPress;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\Types\LaravelEnumType;

final class GraphQLServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     *
     * @throws DefinitionException
     */
    public function boot(TypeRegistry $registry): void
    {
        $enums = [
            Article\Plan::class => 'ArticlePlan',
            Article\PublishType::class => 'ArticlePublishType',
            Article\SortBy::class => 'ArticleSortBy',
            AutoPosting\State::class => 'AutoPostingState',
            Credit\State::class => 'CreditState',
            CustomDomain\Group::class => 'CustomDomainGroup',
            CustomField\GroupType::class => 'CustomFieldGroupType',
            CustomField\Type::class => 'CustomFieldType',
            CustomField\ReferenceTarget::class => 'CustomFieldReferenceTarget',
            Link\Source::class => 'LinkSource',
            Link\Target::class => 'LinkTarget',
            Release\State::class => 'ReleaseState',
            Release\Type::class => 'ReleaseType',
            Scraper\State::class => 'ScraperState',
            Scraper\Type::class => 'ScraperType',
            Site\Hosting::class => 'SiteHosting',
            Subscription\Setup::class => 'SubscriptionSetup',
            Subscription\Type::class => 'SubscriptionType',
            Template\Type::class => 'TemplateType',
            Upload\Image::class => 'UploadImage',
            User\Status::class => 'UserStatus',
            User\Gender::class => 'UserGender',
            Webflow\CollectionType::class => 'WebflowCollectionType',
            Webflow\FieldType::class => 'WebflowFieldType',
            WordPress\OptionalFeature::class => 'WordPressOptionalFeatureType',
        ];

        foreach ($enums as $enum => $name) {
            $registry->register(
                new LaravelEnumType($enum, $name),
            );
        }
    }
}
