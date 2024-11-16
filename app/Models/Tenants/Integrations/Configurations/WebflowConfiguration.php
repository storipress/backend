<?php

declare(strict_types=1);

namespace App\Models\Tenants\Integrations\Configurations;

use App\Enums\CustomField\GroupType;
use App\Enums\CustomField\Type;
use App\Models\Tenants\CustomField;
use App\Models\Tenants\CustomFieldGroup;
use App\Models\Tenants\Integrations\Webflow;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use stdClass;
use Storipress\Webflow\Objects\Collection as CollectionObject;
use Storipress\Webflow\Objects\CollectionField;
use Storipress\Webflow\Objects\Site;

/**
 * @phpstan-import-type CollectionFieldType from CollectionField as WebflowFieldType
 *
 * @phpstan-type WebflowOnboarding array{
 *     site: bool,
 *     detection: array{
 *         site: bool,
 *         collection: bool,
 *         mapping: array{
 *             blog: bool,
 *             author: bool,
 *             desk: bool,
 *             tag: bool,
 *         },
 *     },
 *     collection: array{
 *         blog: bool,
 *         author: bool,
 *         desk: bool,
 *         tag: bool,
 *     },
 *     mapping: array{
 *         blog: bool,
 *         author: bool,
 *         desk: bool,
 *         tag: bool,
 *     },
 * }
 * @phpstan-type BuiltInFields array<string, array<int, array{
 *     name: string,
 *     value: string,
 *     collection?: string,
 * }>>
 * @phpstan-type WebflowCollectionFields non-empty-array<int, array{
 *     id: non-empty-string,
 *     displayName: non-empty-string,
 *     helpText: non-empty-string|null,
 *     isEditable: bool,
 *     isRequired: bool,
 *     slug: non-empty-string,
 *     type: WebflowFieldType,
 *     validations: array<non-empty-string, mixed>,
 *     candidates: array<int, array{
 *         name: non-empty-string,
 *         value: non-empty-string,
 *     }>,
 * }>
 * @phpstan-type WebflowCollection array{
 *     id: non-empty-string,
 *     slug: non-empty-string,
 *     displayName: non-empty-string,
 *     lastUpdated: non-empty-string,
 *     fields: WebflowCollectionFields,
 *     mappings?: array<non-empty-string, non-empty-string|null>,
 * }
 * @phpstan-type WebflowCollections array{
 *     blog?: WebflowCollection,
 *     author?: WebflowCollection,
 *     desk?: WebflowCollection,
 *     tag?: WebflowCollection,
 * }
 */
class WebflowConfiguration extends Configuration
{
    public bool $v2;

    /**
     * @var array<int, string>
     */
    public array $scopes;

    /**
     * @var WebflowOnboarding
     */
    public array $onboarding;

    public bool $expired;

    public bool $first_setup_done;

    /**
     * @var 'any'|'ready'|'published'
     */
    public string $sync_when = 'any';

    public ?string $user_id;

    public ?string $name;

    public ?string $email;

    public ?string $site_id;

    /**
     * This field is designated for external links.
     */
    public ?string $domain;

    public ?string $access_token;

    /**
     * @var WebflowCollections
     */
    public array $collections;

    /**
     * @var array<int, Site>
     */
    public array $raw_sites;

    /**
     * @var array<int, CollectionObject>
     */
    public array $raw_collections;

    /**
     * @var array{
     *     blog: BuiltInFields,
     *     author: BuiltInFields,
     *     desk: BuiltInFields,
     *     tag: BuiltInFields,
     * }
     */
    protected static array $builtInFields = [
        'blog' => [
            Type::text => [
                [
                    'name' => 'Headline',
                    'value' => 'title',
                ],
                [
                    'name' => 'Slug',
                    'value' => 'slug',
                ],
                [
                    'name' => 'Subheading',
                    'value' => 'blurb',
                ],
                [
                    'name' => 'Hero Photo Alt',
                    'value' => 'cover.alt',
                ],
                [
                    'name' => 'Hero Photo Caption',
                    'value' => 'cover.caption',
                ],
                [
                    'name' => 'Search Title',
                    'value' => 'seo.meta.title',
                ],
                [
                    'name' => 'Search Description',
                    'value' => 'seo.meta.description',
                ],
                [
                    'name' => 'Social Title',
                    'value' => 'seo.og.title',
                ],
                [
                    'name' => 'Social Description',
                    'value' => 'seo.og.description',
                ],
            ],

            Type::file => [
                [
                    'name' => 'Hero Photo',
                    'value' => 'cover.url',
                ],
                [
                    'name' => 'Social OG Image',
                    'value' => 'seo.ogImage',
                ],
            ],

            Type::richText => [
                [
                    'name' => 'Content',
                    'value' => 'html',
                ],
            ],

            Type::boolean => [
                [
                    'name' => 'Featured',
                    'value' => 'featured',
                ],
                [
                    'name' => 'Newsletter',
                    'value' => 'newsletter',
                ],
            ],

            Type::date => [
                [
                    'name' => 'Published Date',
                    'value' => 'published_at',
                ],
            ],

            'WebflowReference' => [
                [
                    'name' => 'Desk',
                    'value' => 'desk',
                    'collection' => 'desk',
                ],
                [
                    'name' => 'Authors',
                    'value' => 'authors',
                    'collection' => 'author',
                ],
                [
                    'name' => 'Tags',
                    'value' => 'tags',
                    'collection' => 'tag',
                ],
            ],

            'WebflowMultiReference' => [
                [
                    'name' => 'Desk',
                    'value' => 'desk',
                    'collection' => 'desk',
                ],
                [
                    'name' => 'Authors',
                    'value' => 'authors',
                    'collection' => 'author',
                ],
                [
                    'name' => 'Tags',
                    'value' => 'tags',
                    'collection' => 'tag',
                ],
            ],
        ],

        'author' => [
            Type::text => [
                [
                    'name' => 'Name',
                    'value' => 'name',
                ],
                [
                    'name' => 'Slug',
                    'value' => 'slug',
                ],
                [
                    'name' => 'Contact Email',
                    'value' => 'contact_email',
                ],
                [
                    'name' => 'Job Title',
                    'value' => 'job_title',
                ],
                [
                    'name' => 'Location',
                    'value' => 'location',
                ],
                [
                    'name' => 'Bio',
                    'value' => 'bio',
                ],
            ],

            Type::file => [
                [
                    'name' => 'Avatar',
                    'value' => 'avatar',
                ],
            ],

            Type::url => [
                [
                    'name' => 'Website',
                    'value' => 'website',
                ],
                [
                    'name' => 'Twitter',
                    'value' => 'social.twitter',
                ],
                [
                    'name' => 'Facebook',
                    'value' => 'social.facebook',
                ],
                [
                    'name' => 'Instagram',
                    'value' => 'social.instagram',
                ],
                [
                    'name' => 'LinkedIn',
                    'value' => 'social.linkedin',
                ],
                [
                    'name' => 'YouTube',
                    'value' => 'social.youtube',
                ],
                [
                    'name' => 'Pinterest',
                    'value' => 'social.pinterest',
                ],
                [
                    'name' => 'WhatsApp',
                    'value' => 'social.whatsapp',
                ],
                [
                    'name' => 'Reddit',
                    'value' => 'social.reddit',
                ],
                [
                    'name' => 'TikTok',
                    'value' => 'social.tiktok',
                ],
                [
                    'name' => 'Geneva',
                    'value' => 'social.geneva',
                ],
            ],
        ],

        'desk' => [
            Type::text => [
                [
                    'name' => 'Name',
                    'value' => 'name',
                ],
                [
                    'name' => 'Slug',
                    'value' => 'slug',
                ],
                [
                    'name' => 'Description',
                    'value' => 'description',
                ],
            ],

            'WebflowMultiReference' => [
                [
                    'name' => 'Editors',
                    'value' => 'editors',
                    'collection' => 'author',
                ],
                [
                    'name' => 'Writers',
                    'value' => 'writers',
                    'collection' => 'author',
                ],
            ],
        ],

        'tag' => [
            Type::text => [
                [
                    'name' => 'Name',
                    'value' => 'name',
                ],
                [
                    'name' => 'Slug',
                    'value' => 'slug',
                ],
                [
                    'name' => 'Description',
                    'value' => 'description',
                ],
            ],
        ],
    ];

    /**
     * @var array<non-empty-string, array<int, non-empty-string>>
     */
    protected static array $typeMapping = [
        'PlainText' => [Type::text],
        'RichText' => [Type::richText, Type::text],
        'Image' => [Type::file],
        'MultiImage' => [Type::file],
        'VideoLink' => [Type::url, Type::text],
        'Link' => [Type::url, Type::text],
        'Email' => [Type::text],
        'Phone' => [Type::text],
        'Number' => [Type::number],
        'DateTime' => [Type::date],
        'Switch' => [Type::boolean],
        'Color' => [Type::color],
        'Option' => [Type::select],
        'File' => [Type::file],
        'Reference' => [Type::reference],
        'MultiReference' => [Type::reference],
    ];

    /**
     * Get the Storipress custom field type based on the Webflow type.
     */
    public static function toStoripressType(string $webflow): ?string
    {
        return Arr::first(static::$typeMapping[$webflow] ?? []); // @phpstan-ignore-line
    }

    /**
     * @param  Webflow  $integration
     */
    public static function from($integration): static
    {
        $configuration = $integration->internals ?: [];

        return new static($integration, [
            'v2' => ($configuration['v2'] ?? false) === true,
            'scopes' => $configuration['scopes'] ?? [],
            'onboarding' => static::onboarding($configuration['onboarding'] ?? []),
            'expired' => ($configuration['expired'] ?? false) === true,
            'first_setup_done' => $configuration['first_setup_done'] ?? false,
            'sync_when' => $configuration['sync_when'] ?? 'any',
            'user_id' => $configuration['user_id'] ?? null,
            'name' => $configuration['name'] ?? null,
            'email' => $configuration['email'] ?? null,
            'site_id' => $configuration['site_id'] ?? null,
            'domain' => $configuration['domain'] ?? null,
            'access_token' => $configuration['access_token'] ?? null,
            'collections' => static::collections($configuration['collections'] ?? []),
            'raw_sites' => array_map(function ($data) {
                $encoded = json_encode($data);

                if (is_string($encoded)) {
                    $object = json_decode($encoded);

                    if ($object instanceof stdClass) {
                        return Site::from($object);
                    }
                }

                return new Site(new stdClass());
            }, $configuration['raw_sites'] ?? []),
            'raw_collections' => array_map(function ($data) {
                $encoded = json_encode($data);

                if (is_string($encoded)) {
                    $object = json_decode($encoded);

                    if ($object instanceof stdClass) {
                        return CollectionObject::from($object);
                    }
                }

                return new CollectionObject(new stdClass());
            }, $configuration['raw_collections'] ?? []),
        ]);
    }

    /**
     * @param  WebflowOnboarding  $data
     * @return WebflowOnboarding
     */
    protected static function onboarding(array $data): array
    {
        $default = [
            'site' => false,
            'detection' => [
                'site' => false,
                'collection' => false,
                'mapping' => [
                    'blog' => false,
                    'author' => false,
                    'desk' => false,
                    'tag' => false,
                ],
            ],
            'collection' => [
                'blog' => false,
                'author' => false,
                'desk' => false,
                'tag' => false,
            ],
            'mapping' => [
                'blog' => false,
                'author' => false,
                'desk' => false,
                'tag' => false,
            ],
        ];

        foreach (Arr::dot($default) as $key => $value) {
            $data = Arr::add($data, $key, $value);
        }

        /** @var WebflowOnboarding $data */
        return $data;
    }

    /**
     * @param  WebflowCollections  $data
     * @return WebflowCollections
     */
    protected static function collections(array $data): array
    {
        $data = array_filter($data, fn ($collection) => isset($collection['id'])); // @phpstan-ignore-line

        $query = CustomFieldGroup::withoutEagerLoads()->with(['customFields']);

        $customFields = [
            'blog' => $query->clone()->where('key', '=', 'webflow'),
            'desk' => $query->clone()->where('type', '=', GroupType::deskMetafield()),
            'tag' => $query->clone()->where('type', '=', GroupType::tagMetafield()),
        ];

        $usedCollectionIds = Arr::mapWithKeys($data, function (array $collection, string $key) {
            return [$key => $collection['id']];
        });

        foreach ($data as $key => &$collection) {
            $collection = static::candidates(
                $key,
                $collection,
                $usedCollectionIds,
                ($customFields[$key] ?? null)?->get()->pluck('customFields')->flatten(), // @phpstan-ignore-line
            );
        }

        return $data;
    }

    /**
     * @param  WebflowCollection  $data
     * @param  Collection<int, CustomField>|null  $customFields
     * @param  array<string, string>  $usedCollectionIds
     * @return WebflowCollection
     */
    protected static function candidates(string $collection, array $data, array $usedCollectionIds, ?Collection $customFields): array
    {
        // There are three scenarios for field mapping:
        // 1. Hard Code Fields: These are not processed further. If there's a value, it's written directly.
        // 2. Storipress Built-In Fields: After mapping to the Webflow type, they are written to the supported fields.
        // 3. Storipress Custom Fields: After mapping to the Webflow type, they are written to the supported fields.

        foreach ($data['fields'] as &$field) {
            $field['candidates'] = [];

            // scenario 1
            $key = sprintf('Webflow%s', $field['type']);

            if (isset(static::$builtInFields[$collection][$key])) {
                $candidates = static::$builtInFields[$collection][$key];

                if (Str::contains($field['type'], 'Reference', true)) {
                    $collectionId = data_get($field, 'validations.collectionId');

                    // exclude the collection that are not referenced by this field.
                    $candidates = array_filter(
                        $candidates,
                        fn ($candidate) => isset($candidate['collection'])
                            && isset($usedCollectionIds[$candidate['collection']])
                            && $usedCollectionIds[$candidate['collection']] === $collectionId,
                    );
                }

                array_push(
                    $field['candidates'],
                    ...$candidates,
                );
            }

            // scenario 2
            $mapping = static::$typeMapping[$field['type']] ?? [];

            if (empty($mapping)) {
                continue;
            }

            foreach ($mapping as $type) {
                array_push(
                    $field['candidates'],
                    ...(static::$builtInFields[$collection][$type] ?? []),
                );
            }

            // scenario 3
            if ($customFields === null) {
                continue;
            }

            foreach ($customFields as $customField) {
                if (! in_array($customField->type->value, $mapping, true)) {
                    continue;
                }

                $field['candidates'][] = [
                    'name' => $customField->name,
                    'value' => sprintf('custom_fields.%d', $customField->id),
                ];
            }

            $field['candidates'][] = [
                'name' => '{{Auto-Generated Field}}',
                'value' => '__new__',
            ];
        }

        return $data;
    }
}
