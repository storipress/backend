<?php

use App\Http\Controllers\CaddyOnDemandAsk;
use App\Http\Controllers\FacebookController;
use App\Http\Controllers\Partners\Webflow\OAuthController as WebflowOAuthController;
use App\Http\Controllers\SlackController;
use App\Http\Controllers\TwitterController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', app_url('/'));

Route::get('facebook/oauth')
    ->name('oauth.facebook')
    ->uses([FacebookController::class, 'oauth']);

Route::post('facebook/revoke')
    ->uses([FacebookController::class, 'revoke']);

Route::get('twitter/oauth')
    ->name('oauth.twitter')
    ->uses([TwitterController::class, 'oauth']);

Route::get('slack/oauth')
    ->name('oauth.slack')
    ->uses([SlackController::class, 'oauth']);

Route::get('webflow/oauth')
    ->name('oauth.webflow')
    ->uses(WebflowOAuthController::class);

Route::get('google/oauth', function (\Illuminate\Http\Request $request) {
    return redirect()->away(
        sprintf('https://api.integration.app/oauth-callback?%s', $request->getQueryString() ?: ''),
    );
});

Route::post('slack/events')
    ->uses([SlackController::class, 'events']);

Route::get('caddy/on-demand-ask')
    ->uses(CaddyOnDemandAsk::class);
