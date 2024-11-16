<?php

namespace App\Console\Schedules\FiveMinutes;

use App\Console\Schedules\Command;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Redis as RedisFacade;
use Redis;
use RedisException;
use Throwable;

use function Sentry\captureException;

class SendIngestedDataToAxiom extends Command
{
    /**
     * Execute the console command.
     *
     * @see https://axiom.co/docs/restapi/ingest
     *
     * @throws RedisException
     */
    public function handle(): int
    {
        $token = config('services.axiom.api_token');

        if (! is_not_empty_string($token)) {
            return static::INVALID;
        }

        $redis = RedisFacade::connection('default')->client();

        if (! ($redis instanceof Redis)) {
            return static::FAILURE;
        }

        $api = app('http2')
            ->baseUrl('https://api.axiom.co/v1/datasets/api/ingest')
            ->withToken($token);

        while (true) {
            $data = $redis->lPop('ingest');

            if (! is_not_empty_string($data)) {
                break;
            }

            try {
                $decoded = json_decode($data, true);

                if (! is_array($decoded)) {
                    continue;
                }

                $api->post('/', [$decoded])->throw();

                sleep(2);
            } catch (Throwable $e) {
                if (! ($e instanceof ConnectException || $e instanceof ConnectionException)) {
                    captureException($e);
                }

                $redis->lPush('ingest', $data);

                break;
            }
        }

        return static::SUCCESS;
    }
}
