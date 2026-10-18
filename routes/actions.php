<?php

use Billboard\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

Route::name('billboard.')->middleware('throttle:billboard.tracking')->group(function () {
    Route::post('impressions', [TrackingController::class, 'impressions'])
        ->withoutMiddleware([
            'App\Http\Middleware\VerifyCsrfToken',
            'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
            'Illuminate\Foundation\Http\Middleware\PreventRequestForgery',
        ])
        ->name('impressions');

    Route::get('click/{ad}/{placement}', [TrackingController::class, 'click'])
        ->where(['ad' => '[A-Za-z0-9_-]{1,64}', 'placement' => '[A-Za-z0-9_-]{1,64}'])
        ->name('click');
});
