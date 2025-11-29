<?php

use App\Http\Controllers\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// Login con Google (auto-registro si no existe)
Route::get('/auth/google/login', [GoogleAuthController::class, 'loginWithGoogle'])
    ->name('google.login');

// Conectar Google Calendar (requiere estar logueado)
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
    ->name('google.redirect');

Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->name('google.callback');

Route::get('/auth/google/disconnect', [GoogleAuthController::class, 'disconnect'])
    ->name('google.disconnect');
