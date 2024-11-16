<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailLinkRedirection extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, string $link, string $id, string $salt, string $signature): RedirectResponse
    {
        $known = hmac(['id' => $id, 'link' => $link, 'salt' => $salt], true, 'md5');

        abort_unless(hash_equals($known, $signature), 404);

        $url = base64_decode(rawurldecode($link));

        // @todo log click events

        return response()->redirectTo($url, 301);
    }
}
