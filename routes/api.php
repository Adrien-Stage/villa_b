<?php

use App\Http\Controllers\Api\PublicBookingController;
use App\Http\Controllers\Api\PublicPingController;
use App\Http\Controllers\Api\PublicRestaurantMenuController;
use App\Http\Controllers\Api\PublicRoomController;
use App\Http\Controllers\Api\ReportingController;
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

// ==========================================
// API REPORTING BUSINESS — consommée par la console business de pms.
// Données financières sensibles : jamais publiques, protégées par un jeton
// de service (Authorization: Bearer REPORTING_SECRET). Lecture seule.
// ==========================================
Route::prefix('reporting')->middleware('reporting.token')->group(function () {
    Route::get('/summary',    [ReportingController::class, 'summary'])->name('api.reporting.summary');
    Route::get('/revenue',    [ReportingController::class, 'revenue'])->name('api.reporting.revenue');
    Route::get('/cash-audit', [ReportingController::class, 'cashAudit'])->name('api.reporting.cash-audit');
    Route::get('/expenses',   [ReportingController::class, 'expenses'])->name('api.reporting.expenses');
    Route::get('/invoices',   [ReportingController::class, 'invoices'])->name('api.reporting.invoices');
    Route::get('/staff',      [ReportingController::class, 'staff'])->name('api.reporting.staff');
    Route::get('/alerts',     [ReportingController::class, 'alerts'])->name('api.reporting.alerts');
});
