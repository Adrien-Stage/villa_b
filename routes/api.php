<?php

use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\PermissionMatrixController;
use App\Http\Controllers\Api\PublicBookingController;
use App\Http\Controllers\Api\PublicPingController;
use App\Http\Controllers\Api\PublicRestaurantMenuController;
use App\Http\Controllers\Api\PublicRoomController;
use App\Http\Controllers\Api\ReportingController;
use App\Http\Controllers\Api\StaffAccountController;
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
// ==========================================
// ORCHESTRATION — canal de la console d'orchestration (wetchah_erp).
// Matrice des droits, comptes administrateurs, départements. Gardé par le
// jeton que seule la console détient (ORCHESTRATION_SECRET), pas par le module
// « api » : cette option commerciale coupée, la console ne pourrait plus
// administrer l'établissement.
// ==========================================
// Matrice : la console n'écrit que sa propre couche ; les écarts posés dans
// l'établissement et les dérogations nominatives lui restent étrangers.
Route::prefix('permissions')->middleware('orchestration.token:repli-reporting')->group(function () {
    Route::get('/matrice', [PermissionMatrixController::class, 'show'])->name('api.permissions.matrice');
    Route::put('/matrice', [PermissionMatrixController::class, 'update'])->name('api.permissions.matrice.update');
    Route::post('/matrice/apercu', [PermissionMatrixController::class, 'apercu'])->name('api.permissions.matrice.apercu');
});

Route::middleware('orchestration.token')->group(function () {
    // Les comptes administrateurs ne se créent que d'ici : personne, dans
    // l'établissement, n'accorde un niveau égal au sien.
    Route::get('/comptes', [StaffAccountController::class, 'index'])->name('api.comptes.index');
    Route::post('/comptes/administrateurs', [StaffAccountController::class, 'storeAdmin'])->name('api.comptes.administrateurs.store');
    Route::patch('/comptes/administrateurs/{user}', [StaffAccountController::class, 'updateAdmin'])
        ->whereNumber('user')->name('api.comptes.administrateurs.update');

    Route::get('/departements', [DepartmentController::class, 'index'])->name('api.departements.index');
    Route::post('/departements', [DepartmentController::class, 'store'])->name('api.departements.store');
    Route::put('/departements/{department}', [DepartmentController::class, 'update'])
        ->whereNumber('department')->name('api.departements.update');
    Route::delete('/departements/{department}', [DepartmentController::class, 'destroy'])
        ->whereNumber('department')->name('api.departements.destroy');
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
