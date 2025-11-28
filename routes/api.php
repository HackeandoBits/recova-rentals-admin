<?php

use App\Http\Controllers\Api\V1\BookingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Grupo protegido por Sanctum (La "llave" que generamos antes)
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {

    // El endpoint que recibirá el POST del cliente
    Route::post('/bookings', [BookingController::class, 'store']);

    // Endpoint para consultar horarios ocupados por fecha
    Route::get('/bookings/occupied-time-slots', [BookingController::class, 'getOccupiedTimeSlots']);

    // Endpoint para obtener fechas totalmente bloqueadas
    Route::get('/bookings/blocked-dates', [BookingController::class, 'getBlockedDates']);

});
