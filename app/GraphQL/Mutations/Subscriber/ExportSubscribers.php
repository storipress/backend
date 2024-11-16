<?php

namespace App\GraphQL\Mutations\Subscriber;

use App\Models\Tenants\Subscriber;
use App\Models\Tenants\UserActivity;
use League\Csv\Exception;
use League\Csv\Writer;
use Segment\Segment;

class ExportSubscribers
{
    /**
     * @param  array{}  $args
     *
     * @throws Exception
     */
    public function __invoke($_, array $args): string
    {
        UserActivity::log(
            name: 'member.export',
        );

        Segment::track([
            'userId' => (string) auth()->id(),
            'event' => 'tenant_member_exported',
            'properties' => [
                'tenant_uid' => tenant('id'),
                'tenant_name' => tenant('name'),
            ],
            'context' => [
                'groupId' => tenant('id'),
            ],
        ]);

        $csv = Writer::createFromString();

        $csv->insertOne(['Email', 'First Name', 'Last Name', 'Verified At']);

        $subscribers = Subscriber::all();

        foreach ($subscribers as $subscriber) {
            $csv->insertOne([
                $subscriber->email,
                $subscriber->first_name,
                $subscriber->last_name,
                $subscriber->verified_at?->timestamp,
            ]);
        }

        return $csv->toString();
    }
}
