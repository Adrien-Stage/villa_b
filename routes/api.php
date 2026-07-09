<?php

use App\Http\Controllers\Api\PublicRestaurantMenuController;
use App\Http\Controllers\Api\PublicRoomController;
use Illuminate\Support\Facades\Route;

// ==========================================
// API PUBLIQUE — consommée par le site vitrine (template_site)
// Lecture seule, aucune authentification : pas de données sensibles,
// uniquement du contenu destiné à être affiché publiquement.
// ==========================================
Route::prefix('v1')->group(function () {
    Route::get('/room-types', [PublicRoomController::class, 'index'])->name('api.room-types.index');
    Route::get('/room-types/{roomType}', [PublicRoomController::class, 'show'])->name('api.room-types.show');

    Route::middleware('module:restaurant')->group(function () {
        Route::get('/restaurant/menu', [PublicRestaurantMenuController::class, 'index'])->name('api.restaurant.menu');
    });
});
