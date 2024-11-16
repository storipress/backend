<?php

namespace App\GraphQL\Mutations\Integration;

use App\Exceptions\BadRequestHttpException;
use App\Exceptions\InvalidCredentialsException;
use App\Models\Tenants\Integration;
use App\SDK\Slack\Slack;
use Illuminate\Support\Arr;

final class GetSlackChannelsList extends IntegrationMutation
{
    /**
     * @param  array<string, mixed>  $args
     * @return array<array{id:string, name:string, is_private:bool}>
     */
    public function __invoke($_, array $args): array
    {
        $this->authorize('read', Integration::class);

        $slack = Integration::find('slack');

        $internals = $slack?->internals;

        $internals = is_array($internals) ? $internals : [];

        /** @var string $botToken */
        $botToken = Arr::get($internals, 'bot_access_token', '');

        if (empty($botToken)) {
            throw new BadRequestHttpException();
        }

        try {
            $channels = (new Slack())->getChannelsList($botToken);
        } catch (\Exception $e) {
            if ($e->getCode() === 401) {
                $this->revokeSlackToken();
            }
            throw new InvalidCredentialsException();
        }

        return $channels;
    }

    protected function revokeSlackToken(): void
    {
        $slack = Integration::find('slack');

        $internals = $slack?->internals;

        $internals = is_array($internals) ? $internals : [];

        $botToken = Arr::get($internals, 'bot_access_token');

        if (is_string($botToken) && ! empty($botToken)) {
            (new Slack())->revoke($botToken);
        }

        $userToken = Arr::get($internals, 'user_access_token');

        if (is_string($userToken) && ! empty($userToken)) {
            (new Slack())->revoke($userToken);
        }
    }
}
