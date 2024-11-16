<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Models\Tenant;
use App\Models\Tenants\User as TenantUser;
use App\Models\User;
use Bepsvpt\Blurhash\Facades\BlurHash;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

use function Sentry\captureException;

class ImportUsersToPublication extends Command implements PromptsForMissingInput
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'site:import:users {tenant} {file}';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $items = $this->users();

        if ($items === null) {
            return static::FAILURE;
        }

        $tenantId = $this->argument('tenant');

        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($tenantId);

        if (!($tenant instanceof Tenant)) {
            $this->error(
                sprintf('Tenant not found: %s', $tenantId),
            );

            return static::FAILURE;
        }

        $userIds = [];

        foreach ($items as $item) {
            if ($item['email'] === null) {
                $item['email'] = sprintf(
                    'trashed+%s@storipress.com',
                    Str::lower(Str::random(12)),
                );
            }

            $user = User::firstOrNew([
                'email' => $item['email'],
            ], [
                'password' => Hash::make(Str::random()),
                'first_name' => $item['first_name'],
                'last_name' => $item['last_name'],
                'slug' => $item['slug'],
                'bio' => $item['bio'] ?? null,
                'job_title' => $item['job_title'] ?? null,
                'contact_email' => Str::startsWith($item['email'], 'trashed+') ? null : $item['email'],
            ]);

            if ($user->exists) {
                continue;
            }

            $user->socials = array_filter([
                'LinkedIn' => $this->social($item['linkedin'] ?? ''),
                'Facebook' => $this->social($item['facebook'] ?? ''),
                'Twitter' => $this->social($item['twitter'] ?? ''),
                'Instagram' => $this->social($item['instagram'] ?? ''),
            ]);

            $user->save();

            $user->refresh();

            if (is_not_empty_string($item['avatar'] ?? '')) {
                $this->avatar($user->id, $item['avatar']);
            }

            $userIds[] = $user->id;
        }

        if (empty($userIds)) {
            $this->warn('No user were imported.');

            return static::SUCCESS;
        }

        $tenant->users()->attach($userIds, ['role' => 'author']);

        $tenant->run(function () use ($userIds) {
            foreach ($userIds as $userId) {
                TenantUser::create([
                    'id' => $userId,
                    'role' => 'author',
                ]);
            }
        });

        $this->info(
            sprintf('%d users are imported.', count($userIds)),
        );

        return static::SUCCESS;
    }

    /**
     * @return array<int, array{
     *     email: string|null,
     *     first_name: string,
     *     last_name: string,
     *     slug: string,
     *     avatar?: string|null,
     *     bio?: string,
     *     job_title?: string,
     *     linkedin?: string|null,
     *     facebook?: string|null,
     *     twitter?: string|null,
     *     instagram?: string|null,
     * }>|null
     */
    public function users(): ?array
    {
        $file = $this->argument('file');

        if (!is_file($file)) {
            $this->error(
                sprintf('Invalid file path: %s', $file),
            );

            return null;
        }

        $content = file_get_contents($file);

        if ($content === false) {
            $this->error(
                sprintf('Unable to read the file: %s', $file),
            );

            return null;
        }

        $users = json_decode($content, true);

        if (!is_array($users) || empty($users)) {
            $this->error(
                sprintf('Invalid file content: %s', $file),
            );

            return null;
        }

        return $users;
    }

    /**
     * Convert social URL.
     */
    public function social(mixed $value): string
    {
        if (!is_not_empty_string($value)) {
            return '';
        }

        return Str::after($value, 'https://');
    }

    /**
     * Upload user avatar.
     */
    public function avatar(int $userId, string $url): bool
    {
        $path = temp_file();

        $content = file_get_contents($url);

        if ($content === false) {
            return false;
        }

        if (file_put_contents($path, $content) === false) {
            return false;
        }

        $mime = mime_content_type($path);

        if ($mime === false) {
            return false;
        }

        if (!str_starts_with($mime, 'image/')) {
            return false;
        }

        $extension = Arr::first((new MimeTypes())->getExtensions($mime));

        if (!is_string($extension)) {
            return false;
        }

        try {
            $blurhash = BlurHash::encode($path);
        } catch (Throwable $e) {
            captureException($e);
        }

        $size = getimagesize($path);

        if ($size !== false) {
            [$width, $height] = $size;
        }

        $to = sprintf(
            'assets/media/images/%s.%s',
            unique_token(),
            $extension,
        );

        Storage::drive('s3')->put($to, $content);

        Media::create([
            'model_type' => User::class,
            'model_id' => $userId,
            'collection' => 'avatar',
            'token' => unique_token(),
            'tenant_id' => $this->argument('tenant'),
            'path' => $to,
            'mime' => $mime,
            'size' => filesize($path),
            'width' => $width ?? 0,
            'height' => $height ?? 0,
            'blurhash' => $blurhash ?? null,
        ]);

        return true;
    }
}
