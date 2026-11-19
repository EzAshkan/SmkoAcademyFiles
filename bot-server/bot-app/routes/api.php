<?php

use App\Http\Controllers\InternalSendController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Telegram checks the secret header we register with setWebhook (secret_token).
Route::post('/telegram/webhook', WebhookController::class)
    ->middleware('telegram.webhook');

// Messages from the SMKO Files website.
Route::post('/internal/send', InternalSendController::class)
    ->middleware('throttle:60,1');
