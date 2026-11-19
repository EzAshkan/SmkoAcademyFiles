<?php

use App\Http\Controllers\TelegramClaimController;
use Illuminate\Support\Facades\Route;

// Same path the existing bot already calls — no "/api" prefix.
Route::post('/internal/telegram/claim', TelegramClaimController::class)
    ->middleware('throttle:120,1');
