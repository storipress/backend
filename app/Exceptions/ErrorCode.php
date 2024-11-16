<?php

namespace App\Exceptions;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @link https://www.notion.so/storipress/845064e622f94f0d8744681fa586ba60?v=662256cb5d284dcd93f2092678172ab6
 */
class ErrorCode
{
    public const PERMISSION_FORBIDDEN = 1000010;

    public const NOT_FOUND = 1000020;

    public const CUSTOM_DOMAIN_DUPLICATED = 1010010;

    public const CUSTOM_DOMAIN_CONFLICT = 1010020;

    public const CUSTOM_DOMAIN_INVALID_VALUE = 1010030;

    public const CUSTOM_DOMAIN_PAID_REQUIRED = 1010100;

    public const MEMBER_NOT_FOUND = 1020010;

    public const MEMBER_STRIPE_SUBSCRIPTION_CONFLICT = 1020020;

    public const MEMBER_STRIPE_SUBSCRIPTION_IRREVOCABLE = 1020030;

    public const MEMBER_MANUAL_SUBSCRIPTION_CONFLICT = 1020040;

    public const MEMBER_MANUAL_SUBSCRIPTION_NOT_FOUND = 1020050;

    public const ARTICLE_NOT_FOUND = 1030010;

    public const ARTICLE_NOT_PUBLISHED = 1030020;

    public const ARTICLE_SOCIAL_SHARING_INACTIVATED_INTEGRATIONS = 1030210;

    public const ARTICLE_SOCIAL_SHARING_MISSING_CONFIGURATION = 1030220;

    public const DESK_NOT_FOUND = 1040010;

    public const DESK_HAS_SUB_DESKS = 1040020;

    public const DESK_MOVE_TO_SELF = 1040030;

    public const BILLING_UNAUTHORIZED_REQUEST = 2000010;

    public const BILLING_INVALID_PROVIDER = 2000020;

    public const BILLING_FORBIDDEN_REQUEST = 2000030;

    public const BILLING_INTERNAL_ERROR = 2000040;

    public const OAUTH_BAD_REQUEST = 3000010;

    public const OAUTH_INVALID_PAYLOAD = 3000020;

    public const OAUTH_MISSING_CLIENT = 3000030;

    public const OAUTH_MISSING_USER = 3000040;

    public const OAUTH_FORBIDDEN_REQUEST = 3000050;

    public const OAUTH_UNAUTHORIZED_REQUEST = 3000060;

    public const OAUTH_INTERNAL_ERROR = 3000070;

    public const INTEGRATION_FORBIDDEN_REQUEST = 3000080;

    public const SHOPIFY_MISSING_PRODUCTS_SCOPE = 3040010;

    public const SHOPIFY_INTEGRATION_NOT_CONNECT = 3040020;

    public const SHOPIFY_INTERNAL_ERROR = 3040030;

    public const SHOPIFY_MISSING_REQUIRED_SCOPE = 3040040;

    public const SHOPIFY_NOT_ACTIVATED = 3040050;

    public const SHOPIFY_SHOP_ALREADY_CONNECTED = 3040060;

    public const SHOPIFY_CONFLICT_WITH_WEBFLOW = 3040070;

    public const WEBFLOW_INTEGRATION_NOT_CONNECT = 3050010;

    public const WEBFLOW_MISSING_REQUIRED_FIELD = 3050020;

    public const WEBFLOW_MISSING_FIELD_MAPPING_ID = 3050030;

    public const WEBFLOW_MISSING_INTEGRATION_SETTING = 3050040;

    public const WEBFLOW_COLLIDED_SLUG = 3050050;

    public const WEBFLOW_TITLE_ENCODE_ERROR = 3050060;

    public const WEBFLOW_CUSTOM_FIELD_GROUP_CONFLICT = 3050070;

    public const WEBFLOW_INVALID_FIELD_ID = 3050080;

    public const WEBFLOW_FIELD_TYPE_CONFLICT = 3050090;

    public const WEBFLOW_INVALID_ARTICLE_FIELD_ID = 3050100;

    public const WEBFLOW_UNAUTHORIZED = 3050110;

    public const WEBFLOW_INTERNAL_ERROR = 3050120;

    public const WEBFLOW_HIT_RATE_LIMIT = 3050130;

    public const WEBFLOW_CONFLICT_WITH_SHOPIFY = 3050140;

    public const WEBFLOW_UNSUPPORTED_COLLECTION_FIELDS = 3050150;

    public const WEBFLOW_SITE_NOT_PUBLISHED = 3050160;

    public const WEBFLOW_COLLECTION_NOT_PUBLISHED = 3050170;

    public const WEBFLOW_MISSING_SITE_ID = 3050180;

    public const WEBFLOW_MISSING_COLLECTION_ID = 3050190;

    public const WEBFLOW_INVALID_SITE_ID = 3050200;

    public const WEBFLOW_INVALID_DOMAIN = 3050210;

    public const WEBFLOW_INVALID_COLLECTION_ID = 3050220;

    public const WEBFLOW_COLLECTION_ID_CONFLICT = 3050230;

    public const WEBFLOW_DUPLICATE_COLLECTION = 3050240;

    public const ZAPIER_MISSING_CLIENT = 3060010;

    public const ZAPIER_INVALID_PAYLOAD = 3060020;

    public const ZAPIER_INVALID_TOPIC = 3060030;

    public const ZAPIER_ARTICLE_NOT_FOUND = 3060040;

    public const ZAPIER_INTERNAL_ERROR = 3060050;

    public const LINKEDIN_INTEGRATION_NOT_CONNECT = 3070010;

    public const LINKEDIN_POSTING_FAILED = 3070020;

    public const LINKEDIN_IMAGE_UPLOAD_FAILED = 3070030;

    public const LINKEDIN_REFRESH_FAILED = 3070040;

    public const WORDPRESS_INTEGRATION_NOT_CONNECT = 3080010;

    public const WORDPRESS_CONNECT_INVALID_CODE = 3080020;

    public const WORDPRESS_CONNECT_FAILED = 3080030;

    public const WORDPRESS_CONNECT_FAILED_NO_ROUTE = 3080031;

    public const WORDPRESS_CONNECT_FAILED_FORBIDDEN = 3080032;

    public const WORDPRESS_CONNECT_FAILED_INCORRECT_PASSWORD = 3080033;

    public const WORDPRESS_CONNECT_FAILED_INVALID_PAYLOAD = 3080034;

    public const WORDPRESS_CONNECT_FAILED_NO_CLIENT = 3080035;

    public const WORDPRESS_CONNECT_FAILED_INSUFFICIENT_PERMISSION = 3080036;

    public const WORDPRESS_DISCONNECT_FAILED = 3080040;

    public const WORDPRESS_REQUEST_FAILED = 3080050;

    /**
     * @var array<int, string>
     */
    public static array $statusTexts = [
        self::PERMISSION_FORBIDDEN => 'You are not allowed to perform the operation',
        self::NOT_FOUND => 'The resource you are looking for does not exist',
        self::CUSTOM_DOMAIN_DUPLICATED => 'There are duplicated domain names: {domain}',
        self::CUSTOM_DOMAIN_CONFLICT => 'The domain "{domain}" are already been used',
        self::CUSTOM_DOMAIN_INVALID_VALUE => 'You cannot use this domain',
        self::CUSTOM_DOMAIN_PAID_REQUIRED => 'The publication is not in a paid plan',
        self::MEMBER_NOT_FOUND => 'The member cannot be found',
        self::MEMBER_STRIPE_SUBSCRIPTION_CONFLICT => 'The member already has an active subscription',
        self::MEMBER_STRIPE_SUBSCRIPTION_IRREVOCABLE => 'You cannot revoke a subscription that was not manually assigned',
        self::MEMBER_MANUAL_SUBSCRIPTION_CONFLICT => 'The member already possesses an active subscription',
        self::MEMBER_MANUAL_SUBSCRIPTION_NOT_FOUND => 'The member does not have an active subscription',
        self::ARTICLE_NOT_FOUND => 'The article cannot be found',
        self::ARTICLE_NOT_PUBLISHED => 'The article is not published yet',
        self::ARTICLE_SOCIAL_SHARING_INACTIVATED_INTEGRATIONS => 'There are not any activated social sharing integrations',
        self::ARTICLE_SOCIAL_SHARING_MISSING_CONFIGURATION => 'The article social sharing is empty',
        self::DESK_NOT_FOUND => 'The desk cannot be found',
        self::DESK_HAS_SUB_DESKS => 'You cannot move a desk which has sub-desks',
        self::DESK_MOVE_TO_SELF => 'You cannot move a desk to itself',
        self::BILLING_UNAUTHORIZED_REQUEST => 'The request lacks valid authentication credentials',
        self::BILLING_INVALID_PROVIDER => 'You are choosing an invalid billing provider',
        self::BILLING_FORBIDDEN_REQUEST => 'You are not allowed to perform the billing operation',
        self::BILLING_INTERNAL_ERROR => 'Something went wrong in the Storipress internal service',
        self::OAUTH_BAD_REQUEST => 'You are not allowed to perform the OAuth operation',
        self::OAUTH_INVALID_PAYLOAD => 'The OAuth response payload is invalid',
        self::OAUTH_MISSING_CLIENT => 'The OAuth client is missing',
        self::OAUTH_MISSING_USER => 'You do not have authorized user permission during the OAuth flow',
        self::OAUTH_FORBIDDEN_REQUEST => 'You are not allowed to perform the OAuth operation',
        self::OAUTH_UNAUTHORIZED_REQUEST => 'The request lacks valid authentication credentials',
        self::OAUTH_INTERNAL_ERROR => 'Something went wrong in the Storipress internal service',
        self::INTEGRATION_FORBIDDEN_REQUEST => 'The {key} integration is not connected yet',
        self::SHOPIFY_MISSING_PRODUCTS_SCOPE => 'You are not allowed to the Shopify products operation',
        self::SHOPIFY_INTEGRATION_NOT_CONNECT => 'The Shopify integration is not connected yet',
        self::SHOPIFY_INTERNAL_ERROR => 'Something went wrong in the Shopify internal service',
        self::SHOPIFY_MISSING_REQUIRED_SCOPE => 'You are not allowed to the Shopify {scope} operation',
        self::SHOPIFY_NOT_ACTIVATED => 'The Shopify integration is not activated yet',
        self::SHOPIFY_SHOP_ALREADY_CONNECTED => 'The Shopify shop has already been connected',
        self::SHOPIFY_CONFLICT_WITH_WEBFLOW => 'You are not allowed to connect Shopify and Webflow at the same time',
        self::WEBFLOW_INTEGRATION_NOT_CONNECT => 'The webflow integration is not connected yet',
        self::WEBFLOW_MISSING_REQUIRED_FIELD => 'The article is missing the required fields: {fields}',
        self::WEBFLOW_MISSING_FIELD_MAPPING_ID => 'Can not find the corresponding webflow field id for {fields}',
        self::WEBFLOW_MISSING_INTEGRATION_SETTING => 'The webflow integration is not configured correctly, the {fields} is missing',
        self::WEBFLOW_COLLIDED_SLUG => 'The article slug has already existed in the webflow',
        self::WEBFLOW_TITLE_ENCODE_ERROR => 'There may be invalid characters in the article title',
        self::WEBFLOW_CUSTOM_FIELD_GROUP_CONFLICT => 'The custom field group `webflow` already exists',
        self::WEBFLOW_INVALID_FIELD_ID => 'The webflow field id is invalid',
        self::WEBFLOW_FIELD_TYPE_CONFLICT => 'The field types can not matched ({first} vs {second})',
        self::WEBFLOW_INVALID_ARTICLE_FIELD_ID => 'The article field id is invalid',
        self::WEBFLOW_UNAUTHORIZED => 'The webflow connection is expired, please reconnect it',
        self::WEBFLOW_INTERNAL_ERROR => 'Something went wrong in the Storipress internal service',
        self::WEBFLOW_HIT_RATE_LIMIT => 'The webflow api rate limit has been hit, please try again later',
        self::WEBFLOW_CONFLICT_WITH_SHOPIFY => 'You are not allowed to connect Webflow and Shopify at the same time',
        self::WEBFLOW_UNSUPPORTED_COLLECTION_FIELDS => 'The webflow collection fields {fields} are not supported',
        self::WEBFLOW_SITE_NOT_PUBLISHED => 'The webflow site is not published yet',
        self::WEBFLOW_COLLECTION_NOT_PUBLISHED => 'The webflow collection is not published yet',
        self::WEBFLOW_MISSING_SITE_ID => 'The Webflow site id is not set up yet',
        self::WEBFLOW_MISSING_COLLECTION_ID => 'The Webflow collection id is not set up yet',
        self::WEBFLOW_INVALID_SITE_ID => 'The site id is an invalid value',
        self::WEBFLOW_INVALID_DOMAIN => 'The domain is an invalid value',
        self::WEBFLOW_INVALID_COLLECTION_ID => 'The collection id is an invalid value',
        self::WEBFLOW_COLLECTION_ID_CONFLICT => 'The collection id already been used',
        self::WEBFLOW_DUPLICATE_COLLECTION => 'The collection name({name}) or slug({slug}) is already existed.',
        self::ZAPIER_MISSING_CLIENT => 'The api key has expired or is invalid',
        self::ZAPIER_INVALID_PAYLOAD => 'The request payload is invalid',
        self::ZAPIER_INVALID_TOPIC => 'The request topic is invalid',
        self::ZAPIER_ARTICLE_NOT_FOUND => 'The article you are looking for is not found: {key}',
        self::ZAPIER_INTERNAL_ERROR => 'Something went wrong in the Storipress internal service',
        self::LINKEDIN_INTEGRATION_NOT_CONNECT => 'The LinkedIn integration is not connected yet',
        self::LINKEDIN_POSTING_FAILED => 'The LinkedIn posting failed',
        self::LINKEDIN_IMAGE_UPLOAD_FAILED => 'The LinkedIn image upload failed',
        self::LINKEDIN_REFRESH_FAILED => 'Something went wrong in the Storipress internal service',
        self::WORDPRESS_INTEGRATION_NOT_CONNECT => 'The wordpress integration is not connected yet',
        self::WORDPRESS_CONNECT_INVALID_CODE => 'The wordpress code is invalid',
        self::WORDPRESS_CONNECT_FAILED => 'Failed to connect to your WordPress site. Please contact customer support.',
        self::WORDPRESS_CONNECT_FAILED_NO_ROUTE => 'Failed to connect to your WordPress site. Please contact customer support.',
        self::WORDPRESS_CONNECT_FAILED_FORBIDDEN => 'Failed to connect to your WordPress site. Please contact customer support.',
        self::WORDPRESS_CONNECT_FAILED_INCORRECT_PASSWORD => 'Failed to connect to your WordPress site. Please contact customer support.',
        self::WORDPRESS_CONNECT_FAILED_INVALID_PAYLOAD => 'Failed to connect to your WordPress site. Please reinstall the Storipress plugin.',
        self::WORDPRESS_CONNECT_FAILED_NO_CLIENT => 'Failed to connect to your WordPress site. Please reinstall the Storipress plugin.',
        self::WORDPRESS_CONNECT_FAILED_INSUFFICIENT_PERMISSION => 'Failed to connect to your WordPress site. Please make sure you have full permissions and allow REST API access.',
        self::WORDPRESS_DISCONNECT_FAILED => 'Something went wrong when disconnecting wordpress plugin',
        self::WORDPRESS_REQUEST_FAILED => 'Something went wrong when requesting wordpress plugin api',
    ];

    /**
     * @param  array<string, string|string[]>  $pairs
     */
    public static function getMessage(int $code, array $pairs): string
    {
        $message = self::$statusTexts[$code] ?? null;

        if ($message === null) {
            throw new InvalidArgumentException();
        }

        $contents = [];

        foreach ($pairs as $key => $pair) {
            $contents[sprintf('{%s}', $key)] = Arr::join(Arr::wrap($pair), ',');
        }

        return Str::lower(strtr($message, $contents));
    }
}
