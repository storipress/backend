<?php

use App\Http\Controllers\Rest\V1\Publication\StateController;

Route::get('/publication/state')->uses(StateController::class);
