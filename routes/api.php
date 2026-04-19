<?php

use App\Http\Controllers\WebhookController;
use App\Http\Middleware\CheckDuplicatedTransfer;
use Illuminate\Support\Facades\Route;

Route::post('/webhook', [WebhookController::class, 'store'])
    ->middleware(CheckDuplicatedTransfer::class);
