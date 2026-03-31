<?php

use AlRajhi\PaymentGateway\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/alrajhi/webhook', [WebhookController::class, 'handle'])
    ->name('alrajhi.webhook');
