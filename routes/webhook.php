<?php

use App\Http\Controllers\Webhooks\ProphetMailRepliedController;
use App\Http\Controllers\Webhooks\ProphetMailSentController;
use App\Http\Controllers\Webhooks\ShopifyTemplateReleaseController;

Route::post('/shopify-template-release')
    ->uses(ShopifyTemplateReleaseController::class)
    ->withoutMiddleware('api');

Route::post('/prophet-mail-sent')
    ->uses(ProphetMailSentController::class)
    ->withoutMiddleware('api');

Route::post('/prophet-mail-replied')
    ->uses(ProphetMailRepliedController::class)
    ->withoutMiddleware('api');
