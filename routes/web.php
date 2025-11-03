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