<?php

declare(strict_types=1);

namespace App\Jobs\Webflow;

use App\Models\Media;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\Webflow;
use App\Models\Tenants\User as TenantUser;
use App\Models\User;
use App\Observers\RudderStackSyncingObserver;
use Bepsvpt\Blurhash\Facades\BlurHash;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

class PullUsersFromWebflow extends WebflowPullJob
{
    /**
     * {@inheritdoc}
     */
    public string $rateLimiterName = 'webflow-api-unlimited';

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $tenantId,
        public ?string $webflowId = null,
    ) {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function rateLimitingKey(): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function overlappingKey(): string
    {
        return sprintf(
            '%s:%s',
            $this->tenantId,
            $this->webflowId ?: 'all',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function throttlingKey(): string
    {
        return sprintf('webflow:%s:unlimited', $this->tenantId);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($this->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        RudderStackSyncingObserver::mute();

        $tenant->run(function (Tenant $tenant) {
            $webflow = Webflow::retrieve();

            if (! $webflow->is_activated) {
                return;
            }

            $collection = $webflow->config->collections['author'] ?? null;

            if (! is_array($collection)) {
                return;
            }

            if (empty($collection['mappings'])) {
                return;
            }

            $mapping = $this->mapping($collection);

            foreach ($this->items($collection['id']) as $item) {
                $user = TenantUser::withoutEagerLoads()
                    ->with(['parent'])
                    ->where('webflow_id', '=', $item->id)
                    ->first();

                $attributes = [];

                $avatar = null;

                foreach (get_object_vars($item->fieldData) as $slug => $value) {
                    if (! isset($mapping[$slug])) {
                        continue;
                    }

                    $key = $mapping[$slug];

                    if ($key === 'name') {
                        if (is_string($value)) {
                            $names = explode(' ', $value, 2);

                            $attributes['first_name'] = $names[0];

                            $attributes['last_name'] = $names[1] ?? null;
                        }
                    } elseif (Str::startsWith($key, 'social.')) {
                        [$_, $platform] = explode('.', $key, 2);

                        $attributes['socials'][$platform] = $value; // @phpstan-ignore-line
                    } elseif ($key === 'avatar') {
                        if (is_object($value) && isset($value->url)) {
                            $avatar = $value->url;
                        }
                    } else {
                        $attributes[$key] = $value;
                    }
                }

                if ($user instanceof TenantUser) {
                    $user->parent?->update($attributes);
                } else {
                    $parent = User::firstOrCreate([
                        'email' => sprintf('webflow+%s@storipress.com', $item->id),
                    ], [
                        'password' => Hash::make(Str::password()),
                        'signed_up_source' => sprintf('invite:%s', $tenant->id),
                        ...$attributes,
                    ]);

                    $user = TenantUser::firstOrCreate([
                        'id' => $parent->id,
                    ], [
                        'webflow_id' => $item->id,
                        'role' => 'author',
                    ]);

                    if (! $user->wasRecentlyCreated) {
                        $user->update(['webflow_id' => $item->id]);
                    } else {
                        $tenant->users()->attach($parent->id, ['role' => 'author']);
                    }
                }

                if (is_not_empty_string($avatar)) {
                    $this->avatar($user->id, $avatar);
                }

                ingest(
                    data: [
                        'name' => 'webflow.user.pull',
                        'source_type' => 'user',
                        'source_id' => $user->id,
                        'webflow_id' => $item->id,
                    ],
                    type: 'action',
                );
            }
        });

        RudderStackSyncingObserver::unmute();
    }

    /**
     * 下載使用者大頭貼。
     */
    public function avatar(int $userId, string $url): void
    {
        $path = temp_file();

        if (file_put_contents($path, file_get_contents($url)) === false) {
            return;
        }

        $mime = mime_content_type($path);

        if ($mime === false) {
            return;
        }

        $extension = Arr::first((new MimeTypes())->getExtensions($mime));

        if (! is_not_empty_string($extension)) {
            return;
        }

        $to = sprintf(
            'assets/media/images/%s.%s',
            unique_token(),
            $extension,
        );

        $fp = fopen($path, 'r');

        if ($fp === false) {
            return;
        }

        Storage::drive('s3')->put($to, $fp);

        $size = getimagesize($path);

        if ($size !== false) {
            [$width, $height] = $size;
        }

        try {
            $blurhash = BlurHash::encode($path);
        } catch (Throwable) {
            // ignore
        }

        Media::create([
            'token' => unique_token(),
            'tenant_id' => tenant('id'),
            'model_type' => User::class,
            'model_id' => $userId,
            'collection' => 'avatar',
            'path' => $to,
            'mime' => $mime,
            'size' => filesize($path),
            'width' => $width ?? 0,
            'height' => $height ?? 0,
            'blurhash' => $blurhash ?? null,
        ]);
    }
}
