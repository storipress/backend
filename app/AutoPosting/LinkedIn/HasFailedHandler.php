<?php

namespace App\AutoPosting\LinkedIn;

use App\Mail\AutoPostingFailedMail;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Sentry\State\Scope;
use Throwable;
use Webmozart\Assert\Assert;

use function Sentry\captureException;
use function Sentry\withScope;

trait HasFailedHandler
{
    public function logFailed(Throwable $e, string $layer): void
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $message = sprintf('[failed]: %s', $e->getMessage());

        // unexpected errors.
        if (! ($e instanceof RequestException)) {
            return;
        }

        $code = $e->getCode();

        $fullMessage = $e->response->body();

        $headers = $e->response->headers();

        $content = [
            'tenant' => $tenant->getKey(),
            'platform' => 'linkedin',
            'layer' => $layer,
            'full_message' => $fullMessage,
        ];

        match ($code) {
            // rate limit
            429 => Log::debug($message, array_merge($content, ['headers' => $headers])),
            default => Log::debug($message, $content),
        };
    }

    public function reportFailed(Throwable $e): void
    {
        $tenant = tenant_or_fail();

        withScope(function (Scope $scope) use ($e): void {
            $scope->setContext('debug', [
                'platform' => 'linkedin',
            ]);

            captureException($e);
        });

        if (! ($e instanceof RequestException)) {
            return;
        }

        $code = $e->getCode();

        switch ($code) {
            case 401: // unauthorized
                $hint = 'Token is invalid. Please connect LinkedIn integration.';

                Integration::find('linkedin')?->revoke();

                break;

            default:
                return;
        }

        Mail::to($tenant->owner->email)->send(
            new AutoPostingFailedMail('LinkedIn', $hint),
        );
    }
}
