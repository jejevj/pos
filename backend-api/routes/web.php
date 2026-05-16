<?php

use App\Http\Controllers\OrderTrackingPreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// ── Public order-tracking link preview (Open Graph meta for WhatsApp etc.) ──
// Browsers JS-redirect to the Vue SPA route (?spa=1 makes nginx hand the
// request to the frontend container instead of looping back here).
Route::get('/track/{outletId}/{orderCode}/cover', [OrderTrackingPreviewController::class, 'cover'])
    ->where('outletId',  '[A-Za-z0-9]+')
    ->where('orderCode', '[A-Za-z0-9._-]+');

Route::get('/track/{outletId}/{orderCode}', [OrderTrackingPreviewController::class, 'show'])
    ->where('outletId',  '[A-Za-z0-9]+')
    ->where('orderCode', '[A-Za-z0-9._-]+');
