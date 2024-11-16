<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\CollectionConnected;

use App\Events\Partners\Webflow\CollectionConnected;
use App\Listeners\Traits\HasIngestHelper;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\Configurations\WebflowConfiguration;
use App\Models\Tenants\Integrations\Webflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type WebflowCollection from WebflowConfiguration
 */
class MapCollectionFields implements ShouldQueue
{
    use HasIngestHelper;
    use InteractsWithQueue;

    /**
     * @var array<string, array<string, array{
     *     type: array<int, WebflowCollection['fields'][0]['type']>,
     *     candidate: array<int, string>,
     * }>>
     */
    protected array $mapping = [
        'blog' => [
            'title' => [
                'type' => ['PlainText'],
                'candidate' => ['title', 'headline', 'name'],
            ],
            'slug' => [
                'type' => ['PlainText'],
                'candidate' => ['slug'],
            ],
            'blurb' => [
                'type' => ['RichText', 'PlainText'],
                'candidate' => ['blurb', 'lede', 'subheading', 'excerpt', 'summary'],
            ],
            'cover.url' => [
                'type' => ['Image', 'Link'],
                'candidate' => ['cover', 'hero_photo', 'main_photo', 'main_image'],
            ],
            'html' => [
                'type' => ['RichText', 'PlainText'],
                'candidate' => ['body', 'content'],
            ],
            'featured' => [
                'type' => ['Switch'],
                'candidate' => ['feature'],
            ],
            'newsletter' => [
                'type' => ['Switch'],
                'candidate' => ['newsletter'],
            ],
            'published_at' => [
                'type' => ['DateTime'],
                'candidate' => ['published_at', 'published_date', 'publish_at', 'publish_date', 'posted_at', 'posted_date', 'post_at', 'post_date'],
            ],
            'seo.meta.title' => [
                'type' => ['PlainText'],
                'candidate' => ['search_title', 'seo_title'],
            ],
            'seo.meta.description' => [
                'type' => ['PlainText'],
                'candidate' => ['search_description', 'seo_description'],
            ],
            'seo.og.title' => [
                'type' => ['PlainText'],
                'candidate' => ['social_title'],
            ],
            'seo.og.description' => [
                'type' => ['PlainText'],
                'candidate' => ['social_description'],
            ],
            //            'authors' => [
            //                'type' => ['MultiReference'],
            //                'candidate' => ['author', 'byline'],
            //            ],
            //            'desk' => [
            //                'type' => ['Reference'],
            //                'candidate' => ['desk', 'categor'],
            //            ],
            //            'tags' => [
            //                'type' => ['MultiReference'],
            //                'candidate' => ['tag'],
            //            ],
        ],
        'author' => [
            'name' => [
                'type' => ['PlainText'],
                'candidate' => ['name'],
            ],
            'slug' => [
                'type' => ['PlainText'],
                'candidate' => ['slug'],
            ],
            'contact_email' => [
                'type' => ['Email', 'PlainText'],
                'candidate' => ['email', 'contact'],
            ],
            'job_title' => [
                'type' => ['PlainText'],
                'candidate' => ['title'],
            ],
            'website' => [
                'type' => ['Link', 'PlainText'],
                'candidate' => ['site'],
            ],
            'location' => [
                'type' => ['PlainText'],
                'candidate' => ['location', 'from', 'where'],
            ],
            'bio' => [
                'type' => ['RichText', 'PlainText'],
                'candidate' => ['bio'],
            ],
            'avatar' => [
                'type' => ['Image', 'Link'],
                'candidate' => ['avatar', 'picture', 'photo'],
            ],
            'social.twitter' => [
                'type' => ['Link', 'PlainText'],
                'candidate' => ['twitter'],
            ],
            'social.facebook' => [
                'type' => ['Link', 'PlainText'],
                'candidate' => ['facebook'],
            ],
            'social.instagram' => [
                'type' => ['Link', 'PlainText'],
                'candidate' => ['instagram'],
            ],
            'social.linkedin' => [
                'type' => ['Link', 'PlainText'],
                'candidate' => ['linkedin'],
            ],
            'social.youtube' => [
                'type' => ['Link', 'PlainText'],
                'candidate' => ['youtube'],
            ],
            'social.pinterest' => [
                'type' => ['Link', 'PlainText'],
                'candidate' => ['pinterest'],
            ],
            'social.whatsapp' => [
                'type' => ['Link', 'PlainText'],
                'candidate' => ['whatsapp'],
            ],
            'social.reddit' => [
                'type' => ['Link', 'PlainText'],
                'candidate' => ['reddit'],
            ],
            'social.tiktok' => [
                'type' => ['Link', 'PlainText'],
                'candidate' => ['tiktok'],
            ],
            'social.geneva' => [
                'type' => ['Link', 'PlainText'],
                'candidate' => ['geneva'],
            ],
        ],
        'desk' => [
            'name' => [
                'type' => ['PlainText'],
                'candidate' => ['name'],
            ],
            'slug' => [
                'type' => ['PlainText'],
                'candidate' => ['slug'],
            ],
            'description' => [
                'type' => ['PlainText'],
                'candidate' => ['description'],
            ],
            //            'editors' => [
            //                'type' => ['MultiReference'],
            //                'candidate' => ['editor'],
            //            ],
            //            'writers' => [
            //                'type' => ['MultiReference'],
            //                'candidate' => ['writer'],
            //            ],
        ],
        'tag' => [
            'name' => [
                'type' => ['PlainText'],
                'candidate' => ['name'],
            ],
            'slug' => [
                'type' => ['PlainText'],
                'candidate' => ['slug'],
            ],
            'description' => [
                'type' => ['PlainText'],
                'candidate' => ['description'],
            ],
        ],
    ];

    /**
     * Handle the event.
     */
    public function handle(CollectionConnected $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () use ($event) {
            $webflow = Webflow::retrieve();

            $collection = $webflow->config->collections[$event->collectionKey] ?? null;

            if ($collection === null) {
                return;
            }

            $webflow->config->update([
                'onboarding' => [
                    'detection' => [
                        'mapping' => [
                            $event->collectionKey => true,
                        ],
                    ],
                ],
            ]);

            $mappings = $collection['mappings'] ?? [];

            foreach ($collection['fields'] as $field) {
                // skip fields that are already mapped
                if (isset($mappings[$field['id']])) {
                    continue;
                }

                $mappings[$field['id']] = $this->guess(
                    $event->collectionKey,
                    $field,
                );
            }

            $webflow->config->update([
                'onboarding' => [
                    'detection' => [
                        'mapping' => [
                            $event->collectionKey => false,
                        ],
                    ],
                ],
                'collections' => [
                    $event->collectionKey => [
                        'mappings' => $mappings,
                    ],
                ],
            ]);

            $this->ingest($event);
        });
    }

    /**
     * @param WebflowCollection['fields'][0] $field
     */
    protected function guess(string $collection, array $field): ?string
    {
        foreach ($this->mapping[$collection] as $key => $config) {
            // ignore types that don't match
            if (!in_array($field['type'], $config['type'], true)) {
                continue;
            }

            $name = Str::snake(Str::lower($field['displayName']));

            // align using the field name
            if (!Str::contains($name, $config['candidate'], true)) {
                continue;
            }

            return $key;
        }

        return null;
    }
}
