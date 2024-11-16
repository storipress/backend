<?php

declare(strict_types=1);

namespace App\Http\Controllers\Partners\Webflow;

use App\Http\Controllers\Partners\PartnerController;
use Illuminate\Http\Request;

abstract class WebflowController extends PartnerController
{
    /**
     * Validate webhook requests.
     */
    protected function verifyRequest(Request $request): bool
    {
        $timestamp = $request->header('x-webflow-timestamp');

        if (!is_not_empty_string($timestamp)) {
            return false;
        }

        // dismiss if timestamp exceeds 5 minutes
        if (((now()->getTimestampMs() - (int) $timestamp)) > 5 * 60 * 1000) {
            return false;
        }

        $secret = config('services.webflow.client_secret');

        if (!is_not_empty_string($secret)) {
            return false;
        }

        $signature = $request->header('x-webflow-signature');

        if (!is_not_empty_string($signature) || strlen($signature) !== 64) {
            return false;
        }

        $content = sprintf('%s:%s', $timestamp, $request->getContent());

        $known = hash_hmac(
            'sha256',
            $content,
            $secret,
        );

        return hash_equals($known, $signature);
    }
}
