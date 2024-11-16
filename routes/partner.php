<?php

use App\Http\Controllers\Partners\LinkedIn\ConnectController as LinkedInConnectController;
use App\Http\Controllers\Partners\LinkedIn\OauthController as LinkedinOauthController;
use App\Http\Controllers\Partners\Shopify\ConnectController as ShopifyConnectController;
use App\Http\Controllers\Partners\Shopify\ConnectReauthorizeController as ShopifyConnectReauthorizeController;
use App\Http\Controllers\Partners\Shopify\EventsController as ShopifyEventsController;
use App\Http\Controllers\Partners\Shopify\InstallController as ShopifyInstallController;
use App\Http\Controllers\Partners\Shopify\OauthController as ShopifyOauthController;
use App\Http\Controllers\Partners\Webflow\EventsController as WebflowEventsController;
use App\Http\Controllers\Partners\WordPress\EventsController as WordPressEventsController;
use App\Http\Controllers\Partners\Zapier\AuthController as ZapierAuthController;
use App\Http\Controllers\Partners\Zapier\CreateArticleController as ZapierCreateArticleController;
use App\Http\Controllers\Partners\Zapier\CreateSubscriberController as ZapierCreateSubscriberController;
use App\Http\Controllers\Partners\Zapier\CreateWebhookController as ZapierCreateWebhookController;
use App\Http\Controllers\Partners\Zapier\PublishArticleController as ZapierPublishArticleController;
use App\Http\Controllers\Partners\Zapier\RemoveWebhookController as ZapierRemoveWebhookController;
use App\Http\Controllers\Partners\Zapier\SearchArticleController as ZapierSearchArticleController;
use App\Http\Controllers\Partners\Zapier\UnpublishArticleController as ZapierUnpublishArticleController;
use App\Http\Controllers\Partners\Zapier\WebhookPerformController as ZapierWebhookPerformController;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

Route::prefix('shopify')->group(function () {
    Route::middleware(InitializeTenancyByRequestData::class)
        ->get('connect')
        ->uses(ShopifyConnectController::class);

    Route::middleware(InitializeTenancyByRequestData::class)
        ->get('connect-reauthorize')
        ->name('shopify.connect.reauthorize')
        ->uses(ShopifyConnectReauthorizeController::class);

    Route::get('install')
        ->uses(ShopifyInstallController::class);

    Route::get('oauth')
        ->name('oauth.shopify')
        ->uses(ShopifyOauthController::class);

    Route::post('events')
        ->name('shopify.events')
        ->uses(ShopifyEventsController::class);
});

Route::prefix('zapier')->group(function () {
    Route::get('auth')
        ->name('auth.zapier')
        ->uses(ZapierAuthController::class);

    Route::post('webhook')
        ->name('zapier.webhooks.store')
        ->uses(ZapierCreateWebhookController::class);

    Route::delete('webhook')
        ->name('zapier.webhooks.destroy')
        ->uses(ZapierRemoveWebhookController::class);

    Route::get('webhook/perform')
        ->name('zapier.webhooks.perform')
        ->uses(ZapierWebhookPerformController::class);

    Route::post('article/search')
        ->name('zapier.article.search')
        ->uses(ZapierSearchArticleController::class);

    Route::post('article/create')
        ->name('zapier.article.create')
        ->uses(ZapierCreateArticleController::class);

    Route::post('article/publish')
        ->name('zapier.article.publish')
        ->uses(ZapierPublishArticleController::class);

    Route::post('article/unpublish')
        ->name('zapier.article.unpublish')
        ->uses(ZapierUnpublishArticleController::class);

    Route::post('subscriber/create')
        ->name('zapier.subscriber.create')
        ->uses(ZapierCreateSubscriberController::class);
});

Route::prefix('pabbly-connect')->group(function () {
    Route::get('auth')
        ->name('auth.pabbly-connect')
        ->uses(ZapierAuthController::class);

    Route::post('webhook')
        ->name('pabbly-connect.webhooks.store')
        ->uses(ZapierCreateWebhookController::class);

    Route::delete('webhook')
        ->name('pabbly-connect.webhooks.destroy')
        ->uses(ZapierRemoveWebhookController::class);

    Route::get('webhook/perform')
        ->name('pabbly-connect.webhooks.perform')
        ->uses(ZapierWebhookPerformController::class);

    Route::post('article/search')
        ->name('pabbly-connect.article.search')
        ->uses(ZapierSearchArticleController::class);

    Route::post('article/create')
        ->name('pabbly-connect.article.create')
        ->uses(ZapierCreateArticleController::class);

    Route::post('article/publish')
        ->name('pabbly-connect.article.publish')
        ->uses(ZapierPublishArticleController::class);

    Route::post('article/unpublish')
        ->name('pabbly-connect.article.unpublish')
        ->uses(ZapierUnpublishArticleController::class);

    Route::post('subscriber/create')
        ->name('pabbly-connect.subscriber.create')
        ->uses(ZapierCreateSubscriberController::class);
});

Route::prefix('linkedin')->group(function () {
    Route::middleware(InitializeTenancyByRequestData::class)
        ->get('connect')
        ->uses(LinkedInConnectController::class);

    Route::get('oauth')
        ->name('oauth.linkedin')
        ->uses(LinkedInOauthController::class);
});

Route::prefix('webflow')->group(function () {
    Route::redirect(
        'install',
        app_url('/redirect', ['to' => 'choose-publication', 'integration' => 'webflow', 'client_id' => '_']),
    );

    Route::post('events')
        ->name('webflow.events')
        ->uses(WebflowEventsController::class);
});

Route::prefix('wordpress')->group(function () {
    Route::post('events')
        ->name('wordpress.events')
        ->uses(WordPressEventsController::class);
});
