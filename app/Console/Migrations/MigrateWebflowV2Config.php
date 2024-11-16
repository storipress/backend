<?php

declare(strict_types=1);

namespace App\Console\Migrations;

use App\Models\Tenant;
use App\Models\Tenants\Integrations\Webflow;
use Illuminate\Console\Command;

class MigrateWebflowV2Config extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:webflow-v2-config';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        runForTenants(function (Tenant $tenant) {
            $webflow = Webflow::retrieve();

            $data = $webflow->data ?: [];

            if (empty($data)) {
                return;
            }

            $config = $webflow->internals ?: [];

            $v2 = $config['v2'] ?? false;

            if (! $v2) {
                return;
            }

            $api = app('webflow')->setToken($config['access_token'])->collection();

            $config['expired'] = false;

            $config['first_setup_done'] = false;

            $config['domain'] = $data['domain'] ?? null;

            $config['site_id'] = $data['site_id'] ?? null;

            $config['scopes'] = [
                'authorized_user:read',
                'sites:read',
                'sites:write',
                'pages:read',
                'pages:write',
                'custom_code:read',
                'custom_code:write',
                'cms:read',
                'cms:write',
            ];

            if ($config['site_id']) {
                $origins = $config['collections'] ?? [];

                $collections = [];

                $types = [
                    'blog' => [
                        'key' => 'collection_id',
                        'transform' => [
                            '.title' => 'title',
                            '.blurb' => 'blurb',
                            '.slug' => 'slug',
                            '.cover' => 'cover.url',
                            '.body' => 'html',
                            '.featured' => 'featured',
                            '.newsletter' => 'newsletter',
                            '.published_at' => 'published_at',
                            '.search_title' => 'seo.meta.title',
                            '.search_description' => 'seo.meta.description',
                            '.authors' => 'authors',
                            '.desk' => 'desk',
                            '.tags' => 'tags',
                        ],
                    ],
                    'author' => [
                        'key' => 'author_collection_id',
                        'transform' => [
                            '.bio' => 'bio',
                            '.bio_summary' => 'bio',
                            '.avatar' => 'avatar',
                            '.contact_email' => 'contact_email',
                            '.job_title' => 'job_title',
                            '.twitter' => 'social.twitter',
                            '.facebook' => 'social.facebook',
                            '.instagram' => 'social.instagram',
                            '.linkedin' => 'social.linkedin',
                        ],
                    ],
                    'desk' => [
                        'key' => 'desk_collection_id',
                        'transform' => [
                            '.description' => 'description',
                            '.editors' => 'editors',
                            '.writers' => 'writers',
                        ],
                    ],
                    'tag' => [
                        'key' => 'tag_collection_id',
                        'transform' => [],
                    ],
                ];

                foreach ($types as $type => $item) {
                    if (! isset($data[$item['key']])) {
                        continue;
                    }

                    $collections[$type] = $api->get($data[$item['key']]);

                    foreach ($origins as $origin) {
                        if ($origin['id'] !== $data[$item['key']]) {
                            continue;
                        }

                        $mappings = [];

                        foreach ($origin['mappings'] ?? [] as $mapping) {
                            $mappings[$mapping['key']] = $item['transform'][$mapping['value']] ?? null;
                        }

                        // @phpstan-ignore-next-line
                        foreach ($collections[$type]->fields as $field) {
                            if ($field->slug === 'name') {
                                if (! isset($mappings[$field->id])) {
                                    $mappings[$field->id] = 'name';
                                }
                            }

                            if ($field->slug === 'slug') {
                                if (! isset($mappings[$field->id])) {
                                    $mappings[$field->id] = 'slug';
                                }
                            }
                        }

                        $collections[$type] = json_decode(
                            json_encode($collections[$type]), // @phpstan-ignore-line
                            true,
                        );

                        $collections[$type]['mappings'] = $mappings; // @phpstan-ignore-line
                    }
                }

                $config['collections'] = $collections;
            }

            $webflow->update([
                'data' => [],
                'internals' => $config,
            ]);

            $webflowData = $tenant->webflow_data;

            $webflowData['site_id'] = $config['site_id'];

            $tenant->update(['webflow_data' => $webflowData]);
        });

        return static::SUCCESS;
    }
}
