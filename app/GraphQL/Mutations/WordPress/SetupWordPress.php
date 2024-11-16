<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\WordPress;

use App\Enums\WordPress\OptionalFeature;
use App\Events\Partners\WordPress\Connected;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\Tenant;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Sentry\State\Scope;
use Storipress\WordPress\Exceptions\CannotViewUserException;
use Storipress\WordPress\Exceptions\IncorrectPasswordException;
use Storipress\WordPress\Exceptions\NoRouteException;
use Storipress\WordPress\Exceptions\NotFoundException;
use Storipress\WordPress\Exceptions\RestForbiddenException;
use Throwable;

use function Sentry\captureException;
use function Sentry\withScope;

final readonly class SetupWordPress
{
    /**
     * @param  array{
     *     code: string
     * }  $args
     */
    public function __invoke(null $_, array $args): bool
    {
        $tenant = tenant();

        if (! ($tenant instanceof Tenant)) {
            throw new HttpException(ErrorCode::NOT_FOUND);
        }

        $decoded = base64_decode(rawurldecode($args['code']), true);

        if ($decoded === false) {
            throw new HttpException(ErrorCode::WORDPRESS_CONNECT_INVALID_CODE);
        }

        $data = json_decode($decoded, true);

        if (! is_array($data)) {
            throw new HttpException(ErrorCode::WORDPRESS_CONNECT_INVALID_CODE);
        }

        $validator = Validator::make($data, $this->rules());

        if ($validator->fails()) {
            throw new HttpException(ErrorCode::WORDPRESS_CONNECT_INVALID_CODE);
        }

        $payload = [
            'version' => $data['version'],
            'access_token' => $data['token'],
            'email' => $data['email'],
            'hash_key' => $data['hash_key'],
            'username' => $data['username'],
            'user_id' => $data['user_id'],
            'url' => $data['url'],
            'site_name' => $data['site_name'],
            'prefix' => $data['rest_prefix'] ?? '',
            'permalink_structure' => $data['permalink_structure'] ?? '',
            'feature' => [
                OptionalFeature::site => false,
                OptionalFeature::acf => $data['activated_plugins']['acf'] ?? false,
                OptionalFeature::acfPro => $data['activated_plugins']['acf_pro'] ?? false,
                OptionalFeature::yoastSeo => $data['activated_plugins']['yoast_seo'] ?? false,
                OptionalFeature::rankMath => $data['activated_plugins']['rank_math'] ?? false,
            ],
        ];

        $client = app('wordpress')
            ->setUrl($payload['url'])
            ->setUsername($payload['username'])
            ->setPassword($payload['access_token']);

        if (is_not_empty_string($payload['prefix'])) {
            $client->setPrefix($payload['prefix']);
        }

        if (is_not_empty_string($payload['permalink_structure'])) {
            $client->prettyUrl();
        }

        try {
            $client->user()->list(['page' => 1, 'per_page' => 1, 'roles' => ['administrator'], 'context' => 'edit']);

            $client->request()->post('/storipress/connect', [
                'storipress_client' => $tenant->id,
            ]);
        } catch (NotFoundException|NoRouteException) {
            throw new HttpException(ErrorCode::WORDPRESS_CONNECT_FAILED_NO_ROUTE);
        } catch (RestForbiddenException) {
            throw new HttpException(ErrorCode::WORDPRESS_CONNECT_FAILED_FORBIDDEN);
        } catch (IncorrectPasswordException) {
            throw new HttpException(ErrorCode::WORDPRESS_CONNECT_FAILED_INCORRECT_PASSWORD);
        } catch (CannotViewUserException) {
            throw new HttpException(ErrorCode::WORDPRESS_CONNECT_FAILED_INSUFFICIENT_PERMISSION);
        } catch (Throwable $e) {
            $message = $e->getMessage();

            if (Str::contains($message, '4221001')) {
                throw new HttpException(ErrorCode::WORDPRESS_CONNECT_FAILED_INVALID_PAYLOAD);
            } elseif (Str::contains($message, '4222001')) {
                throw new HttpException(ErrorCode::WORDPRESS_CONNECT_FAILED_NO_CLIENT);
            }

            withScope(function (Scope $scope) use ($e, $payload) {
                $scope->setContext('payload', $payload);

                captureException($e);
            });

            throw new HttpException(ErrorCode::WORDPRESS_CONNECT_FAILED);
        }

        Connected::dispatch(
            $tenant->id,
            $payload,
        );

        UserActivity::log(
            name: 'integration.connect',
            data: [
                'key' => 'wordpress',
            ],
        );

        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'version' => 'required|string',
            'token' => 'required|string',
            'email' => 'required|string',
            'hash_key' => 'required|string',
            'username' => 'required|string',
            'user_id' => 'required|int',
            'url' => 'required|url',
            'site_name' => 'required|string',
            'rest_prefix' => 'string|nullable',
            'permalink_structure' => 'string|nullable',
            'activated_plugins' => 'array',
        ];
    }
}
