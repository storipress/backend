<?php

namespace App\AutoPosting\LinkedIn;

use App\Exceptions\ErrorException;
use App\Mail\AutoPostingFailedMail;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Webmozart\Assert\Assert;

trait HasStoppedHandler
{
    public function logStopped(ErrorException $e, string $layer): void
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $message = sprintf('[stopped] %s', $e->getMessage());

        Log::debug($message, [
            'tenant' => $tenant->getKey(),
            'platform' => 'linkedin',
            'layer' => $layer,
            'code' => $e->getCode(),
        ]);
    }

    public function reportStopped(ErrorException $e): void
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $owner = $tenant->owner;

        Mail::to($owner->email)->send(new AutoPostingFailedMail('LinkedIn', $e->getMessage()));
    }
}
