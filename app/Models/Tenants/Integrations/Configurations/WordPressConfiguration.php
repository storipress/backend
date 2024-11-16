<?php

namespace App\Models\Tenants\Integrations\Configurations;

use App\Enums\WordPress\OptionalFeature;
use App\Exceptions\ErrorCode;
use App\Exceptions\ErrorException;
use App\Models\Tenants\Integrations\Integration;

class WordPressConfiguration extends Configuration
{
    public string $version;

    public string $username;

    public int $user_id;

    public string $hash_key;

    public string $email;

    public string $url;

    public string $site_name;

    public string $access_token;

    public string $prefix;

    public string $permalink_structure;

    public bool $expired;

    /**
     * @var array{
     *     site: bool,
     *     acf: bool,
     *     acf_pro: bool,
     *     yoast_seo: bool,
     *     rank_math: bool,
     * }
     */
    public array $feature;

    public static function from(Integration $integration): static
    {
        $configuration = $integration->internals ?: [];

        if (empty($configuration)) {
            throw new ErrorException(ErrorCode::WORDPRESS_INTEGRATION_NOT_CONNECT);
        }

        return new static($integration, [
            'version' => $configuration['version'],
            'user_id' => $configuration['user_id'],
            'hash_key' => $configuration['hash_key'],
            'username' => $configuration['username'],
            'email' => $configuration['email'],
            'url' => $configuration['url'],
            'site_name' => $configuration['site_name'],
            'access_token' => $configuration['access_token'],
            'prefix' => $configuration['prefix'] ?? '',
            'permalink_structure' => $configuration['permalink_structure'] ?? '',
            'expired' => $configuration['expired'] ?? false,
            'feature' => [
                OptionalFeature::site => $configuration['feature']['site'] ?? false,
                OptionalFeature::acf => $configuration['feature']['acf'] ?? false,
                OptionalFeature::acfPro => $configuration['feature']['acf_pro'] ?? false,
                OptionalFeature::yoastSeo => $configuration['feature']['yoast_seo'] ?? false,
                OptionalFeature::rankMath => $configuration['feature']['rank_math'] ?? false,
            ],
        ]);
    }
}
