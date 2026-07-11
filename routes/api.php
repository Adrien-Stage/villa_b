<?php

use App\Http\Controllers\Api\PublicBookingController;
use App\Http\Controllers\Api\PublicPingController;
use App\Http\Controllers\Api\PublicRestaurantMenuController;
use App\Http\Controllers\Api\PublicRoomController;
use Illuminate\Support\Facades\Route;

// ==========================================
// API PUBLIQUE — consommée par le site vitrine (template_site)
// Lecture seule, aucune authentification : pas de données sensibles,
// uniquement du contenu destiné à être affiché publiquement.
// ==========================================
Route::prefix('v1')->group(function () {
    Route::get('/ping', PublicPingController::class)->name('api.ping');
    Route::get('/rooms', [PublicRoomController::class, 'rooms'])->name('api.rooms.index');
    Route::get('/rooms/{room}', [PublicRoomController::class, 'roomShow'])->name('api.rooms.show');

    // Demande de réservation depuis le site vitrine (throttle anti-spam)
    Route::post('/bookings', [PublicBookingController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('api.bookings.store');
    Route::get('/room-types', [PublicRoomController::class, 'index'])->name('api.room-types.index');
    Route::get('/room-types/{roomType}', [PublicRoomController::class, 'show'])->name('api.room-types.show');

    Route::middleware('module:restaurant')->group(function () {
        Route::get('/restaurant/menu', [PublicRestaurantMenuController::class, 'index'])->name('api.restaurant.menu');
    });
});
