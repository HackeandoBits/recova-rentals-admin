<?php

use App\Http\Controllers\Api\V1\BookingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rutas públicas (sin autenticación) - Consultas del cliente
Route::prefix('v1')->group(function () {
    // Endpoint para consultar horarios ocupados por fecha
    Route::get('/bookings/occupied-time-slots', [BookingController::class, 'getOccupiedTimeSlots']);

    // Endpoint para obtener fechas totalmente bloqueadas
    Route::get('/bookings/blocked-dates', [BookingController::class, 'getBlockedDates']);
});

// Rutas protegidas por Sanctum (requieren autenticación)
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    // El endpoint que recibirá el POST del cliente para crear reservas
    Route::post('/bookings', [BookingController::class, 'store']);
});
