<?php

use App\Http\Controllers\Assistants\AskGeneralController;
use App\Http\Controllers\Assistants\PatchPromptController;
use App\Http\Controllers\Assistants\SavePromptController;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

Route::middleware(InitializeTenancyByRequestData::class)
    ->group(function () {
        Route::post('ask-general')
            ->uses(AskGeneralController::class);

        Route::post('save-prompt')
            ->uses(SavePromptController::class);

        Route::post('patch-prompt')
            ->uses(PatchPromptController::class);
    });
