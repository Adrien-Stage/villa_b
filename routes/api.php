<?php

use App\Http\Controllers\Api\PublicBookingController;
use App\Http\Controllers\Api\PublicPingController;
use App\Http\Controllers\Api\PublicRestaurantMenuController;
use App\Http\Controllers\Api\PublicRoomController;
use App\Http\Controllers\Api\ReportingController;
use Illuminate\Support\Facades\Route;

// ==========================================
// API PUBLIQUE — consommée par le site vitrine (template_site)
// et applications tierces.
// Lecture seule, aucune authentification : pas de données sensibles,
// uniquement du contenu destiné à être affiché publiquement.
// Conditionnée par l'activation du module 'api' (TENANT_MODULES).
// ==========================================
Route::prefix('v1')->middleware('module:api')->group(function () {
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
// API REPORTING BUSINESS & GRC — consommée par la console business de l'ERP
// et le module de contrôle de gestion (Wetchah_GRC).
// Données financières et d'audit protégées par un jeton
// de service (Authorization: Bearer REPORTING_SECRET).
// Conditionnée par l'activation du module 'api' (TENANT_MODULES).
// ==========================================
// Matrice des droits : lue et écrite par la console d'orchestration, qui
// pilote les rôles ; les dérogations nominatives accordées sur place lui
// restent étrangères.
Route::prefix('permissions')->middleware(['module:api', 'reporting.token'])->group(function () {
    Route::get('/matrice',  [\App\Http\Controllers\Api\PermissionMatrixController::class, 'show'])->name('api.permissions.matrice');
    Route::put('/matrice',  [\App\Http\Controllers\Api\PermissionMatrixController::class, 'update'])->name('api.permissions.matrice.update');
});

Route::prefix('reporting')->middleware(['module:api', 'reporting.token'])->group(function () {
    Route::get('/summary',    [ReportingController::class, 'summary'])->name('api.reporting.summary');
    Route::get('/revenue',    [ReportingController::class, 'revenue'])->name('api.reporting.revenue');
    Route::get('/cash-audit', [ReportingController::class, 'cashAudit'])->name('api.reporting.cash-audit');
    Route::get('/expenses',   [ReportingController::class, 'expenses'])->name('api.reporting.expenses');
    Route::get('/invoices',   [ReportingController::class, 'invoices'])->name('api.reporting.invoices');
    Route::get('/finance',    [ReportingController::class, 'finance'])->name('api.reporting.finance');
    Route::get('/staff',      [ReportingController::class, 'staff'])->name('api.reporting.staff');
    Route::get('/customers',  [ReportingController::class, 'customers'])->name('api.reporting.customers');
    Route::get('/alerts',     [ReportingController::class, 'alerts'])->name('api.reporting.alerts');
});
