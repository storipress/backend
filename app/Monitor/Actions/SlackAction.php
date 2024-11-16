<?php

namespace App\Monitor\Actions;

use App\Monitor\BaseAction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class SlackAction extends BaseAction
{
    /**
     * @param  array{messages:string[], data:array{webhook_url:string}}  $data
     */
    public function run(array $data): void
    {
        $base = Http::connectTimeout(5)
            ->timeout(10)
            ->retry(3, 1000)
            ->withUserAgent('storipress-slack-action/2022-08-11');

        // Rete Limit: 1 per second, 4000 characters per message
        $chunkMessages = array_chunk($data['messages'], 20);

        foreach ($chunkMessages as $chuck) {
            $base->post($data['data']['webhook_url'], [
                'text' => Arr::join($chuck, "\n"),
            ]);

            sleep(1);
        }
    }
}
