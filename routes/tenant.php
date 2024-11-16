<?php

use App\Http\Controllers\ArticleAudits;
use App\Http\Controllers\FacebookController;
use App\Http\Controllers\SlackController;
use App\Http\Controllers\TakeoutController;
use App\Http\Controllers\TwitterController;
use App\Http\Middleware\InternalApiAuthenticate;
use Illuminate\Support\Facades\Route;

Route::get('facebook/connect')
    ->uses([FacebookController::class, 'connect']);

Route::get('twitter/connect')
    ->uses([TwitterController::class, 'connect']);

Route::get('slack/connect')
    ->uses([SlackController::class, 'connect']);

Route::get('takeouts')
    ->uses(TakeoutController::class);

Route::middleware(InternalApiAuthenticate::class)
    ->get('article-audits')
    ->uses(ArticleAudits::class);
