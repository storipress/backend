<?php

namespace App\Console\Commands\Report;

use Illuminate\Console\Command;
use JoliCode\Slack\Api\Model\ChatPostMessagePostResponse200;
use JoliCode\Slack\Api\Model\ChatPostMessagePostResponsedefault;
use JoliCode\Slack\Client;
use Psr\Http\Message\ResponseInterface;

abstract class ReportAnalyticMetrics extends Command
{
    /**
     * @param  array{thread_ts?: string}  $options
     */
    protected function sendToSlack(string $blocks, array $options = []): ChatPostMessagePostResponse200|ChatPostMessagePostResponsedefault|null|ResponseInterface
    {
        /** @var Client $slack */
        $slack = app('slack');

        return $slack->chatPostMessage(array_merge([
            'channel' => 'CLFCTA59P',
            'blocks' => $blocks,
            'unfurl_links' => false,
        ], $options));
    }
}
