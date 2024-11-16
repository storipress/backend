<?php

declare(strict_types=1);

namespace App\Jobs\Webflow;

use App\Models\Tenants\Integrations\Configurations\WebflowConfiguration;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

/**
 * @phpstan-import-type WebflowCollection from WebflowConfiguration
 */
abstract class WebflowPullJob extends WebflowJob
{
    /**
     * @param  WebflowCollection  $collection
     * @return array<non-empty-string, non-empty-string>
     */
    public function mapping(array $collection): array
    {
        $mapping = [];

        foreach ($collection['fields'] as $field) {
            if (in_array($field['type'], ['User'], true)) {
                continue;
            }

            if (!isset($collection['mappings'][$field['id']])) {
                continue;
            }

            $mapping[$field['slug']] = $collection['mappings'][$field['id']];
        }

        return $mapping;
    }

    /**
     * 將對應網址轉成 file 型態的 custom field value。
     *
     * @return array{
     *     key: string,
     *     url: string,
     *     size: int,
     *     mime_type: string,
     * }
     */
    public function toFile(string $value): array
    {
        $headers = array_change_key_case(
            get_headers($value, true) ?: [],
        );

        $extension = pathinfo($value, PATHINFO_EXTENSION);

        return [
            'key' => Str::after($value, 'https://uploads-ssl.webflow.com/'),
            'url' => $value,
            'size' => (int) ($headers['content-length'] ?? 0),
            'mime_type' => $this->extensionToMime($extension),
        ];
    }

    /**
     * 透過副檔名取得 mime。
     */
    public function extensionToMime(string $ext): string
    {
        // @phpstan-ignore-next-line
        return Arr::first(
            (new MimeTypes())->getMimeTypes($ext),
            default: 'application/octet-stream',
        );
    }
}
