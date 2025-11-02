<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleAuthController;

Route::get('/', function () {
    return view('welcome');
});
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->name('google.redirect');

    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->name('google.callback');

        Route::middleware(['web','auth'])->get('/auth/google/test', function () {
    $cal = app(\App\Services\GoogleCalendarService::class)->forUser(auth()->id());
    $events = $cal->events->listEvents('primary', [
        'singleEvents' => true,
        'orderBy' => 'startTime',
        'timeMin' => now()->toRfc3339String(),
        'maxResults' => 3,
    ]);

    return response()->json([
        'ok' => true,
        'count' => count($events->getItems()),
        'first' => optional($events->getItems()[0] ?? null)->getSummary(),
    ]);
});