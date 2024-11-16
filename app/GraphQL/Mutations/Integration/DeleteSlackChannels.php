<?php

namespace App\GraphQL\Mutations\Integration;

use App\Exceptions\BadRequestHttpException;
use App\Models\Tenants\Integration;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;

final class DeleteSlackChannels extends IntegrationMutation
{
    /**
     * @var string[]
     */
    protected $keys = [
        'stage',
        'published',
    ];

    /**
     * @param  array{key: string, channels: string[]}  $args
     */
    public function __invoke($_, array $args): Integration
    {
        $this->authorize('write', Integration::class);

        if (! in_array($args['key'], $this->keys, true)) {
            throw new BadRequestHttpException();
        }

        /** @var Integration $slack */
        $slack = Integration::find('slack');

        /** @var array{id:string, name:string, avatar:string, published:string[], stage:string[]}|null $data */
        $data = $slack->data;

        if (empty($data)) {
            throw new BadRequestHttpException();
        }

        /** @var string[] $channels */
        $channels = Arr::get($data, $args['key'], []);

        $data[$args['key']] = array_diff($channels, $args['channels']);

        $integration = $this->update('slack', [
            'data' => $data,
        ]);

        UserActivity::log(
            name: 'integration.slack.channels.remove',
            data: [
                'key' => $integration->getKey(),
                'old' => $integration->getChanges()['data'] ?? null,
                'new' => $data,
            ],
        );

        return $integration;
    }
}
