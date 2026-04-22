<?php

use App\Http\Controllers\WebhookController;
use App\Http\Middleware\CheckDuplicatedTransfer;
use App\Http\Middleware\CheckDuplicatedTransferInDatabase;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/payments/phase-1', [WebhookController::class, 'storePhase1']);

Route::post('/webhooks/payments/phase-2', [WebhookController::class, 'storePhase2'])
    ->middleware(CheckDuplicatedTransfer::class);

Route::post('/webhooks/payments/phase-3', [WebhookController::class, 'storePhase3'])
    ->middleware(CheckDuplicatedTransferInDatabase::class);
