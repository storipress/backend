<?php

use App\Http\Controllers\AppSumoNotificationController;
use App\Http\Controllers\AppSumoTokenController;
use App\Http\Controllers\EmailLinkRedirection;
use App\Http\Controllers\GrowthBookWebhook;
use App\Http\Controllers\HocuspocusWebhook;
use App\Http\Controllers\PostmarkWebhook;
use App\Http\Controllers\PusherAppsController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\Testing\FakeAppSumoSignUpCode;
use App\Http\Controllers\Testing\ResetAppSubscription;
use App\Http\Controllers\UnsubscribeFromMailingListController;
use App\Http\Middleware\BuilderAuthenticate;
use App\Http\Middleware\CatchDefinitionException;
use App\Http\Middleware\InternalApiAuthenticate;
use Illuminate\Support\Facades\Route;
use Nuwave\Lighthouse\Http\GraphQLController;

Route::middleware([BuilderAuthenticate::class, CatchDefinitionException::class])
    ->post('/graphql')
    ->uses(GraphQLController::class)
    ->name('graphql.central');

Route::middleware(InternalApiAuthenticate::class)
    ->get('/sites/{site?}', SiteController::class)
    ->withoutMiddleware('api');

Route::middleware(InternalApiAuthenticate::class)
    ->get('/pusher-apps')
    ->uses(PusherAppsController::class);

Route::post('/growthbook-webhook')
    ->uses(GrowthBookWebhook::class)
    ->withoutMiddleware('api');

Route::post('/hocuspocus-webhook')
    ->uses(HocuspocusWebhook::class)
    ->withoutMiddleware('api');

Route::post('/postmark-webhook')
    ->uses(PostmarkWebhook::class)
    ->withoutMiddleware('api');

Route::post('/appsumo-token')
    ->name('appsumo.token')
    ->uses(AppSumoTokenController::class)
    ->withoutMiddleware('api');

Route::post('/appsumo-notification')
    ->name('appsumo.notification')
    ->uses(AppSumoNotificationController::class)
    ->withoutMiddleware('api');

Route::get('/elr/{link}/{id}/{salt}/{signature}')
    ->uses(EmailLinkRedirection::class)
    ->name('elr')
    ->withoutMiddleware('api');

Route::match(['get', 'post'], '/unsubscribe/{payload}')
    ->uses(UnsubscribeFromMailingListController::class)
    ->name('unsubscribe-from-mailing-list');

Route::post('/testing/reset-app-subscription')
    ->middleware(InternalApiAuthenticate::class)
    ->uses(ResetAppSubscription::class);

Route::get('/testing/fake-appsumo-sign-up-code')
    ->middleware(InternalApiAuthenticate::class)
    ->uses(FakeAppSumoSignUpCode::class);
