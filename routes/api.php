<?php

use Illuminate\Support\Facades\Route;

Route::post('/v1/inbound-emails', \App\Http\Controllers\ReceiveInboundEmailController::class)
    ->middleware(['throttle:30,1', \App\Http\Middleware\AuthenticateInboundEmail::class])
    ->name('api.inbound-emails.store');
