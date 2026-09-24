<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BuildingSelectController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\PassController;
use App\Http\Controllers\ScannerController;
use Illuminate\Support\Facades\Route;

// --- Guest ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// --- Authenticated ---
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/select-building', [BuildingSelectController::class, 'show'])->name('building.select');
    Route::post('/select-building', [BuildingSelectController::class, 'store'])->name('building.select.store');

    // Everything below requires a guard to have picked their building first.
    // Admins skip this check entirely (see EnsureBuildingSelected).
    Route::middleware('building.selected')->group(function () {

        Route::get('/', [ScannerController::class, 'index'])->name('scanner.index');
        Route::post('/scan', [ScannerController::class, 'scan'])->name('scanner.scan');

                Route::get('/passes', [PassController::class, 'index'])->name('passes.index');
        // Must stay ABOVE /passes/{pass}, otherwise {pass} swallows "check-duplicate".
        Route::get('/passes/check-duplicate', [PassController::class, 'checkDuplicate'])->name('passes.check-duplicate');
        Route::post('/passes/register', [PassController::class, 'register'])->name('passes.register');
        Route::get('/passes/{pass}', [PassController::class, 'show'])->name('passes.show');
        Route::put('/passes/{pass}/buildings', [PassController::class, 'updateBuildings'])->name('passes.buildings.update');
        Route::post('/passes/{pass}/unassign', [PassController::class, 'unassign'])->name('passes.unassign');
        Route::post('/passes/{pass}/revoke', [PassController::class, 'revoke'])->name('passes.revoke');
        Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
        Route::get('/logs/export', [LogController::class, 'export'])->name('logs.export');
        Route::get('/logs/registrations/export', [LogController::class, 'exportRegistrations'])->name('logs.registrations.export');

        // Admin-only — guards get a 403 even if they hit these URLs directly.
        Route::middleware('admin')->group(function () {
            Route::delete('/logs/purge/range', [LogController::class, 'purgeRange'])->name('logs.purge.range');
            Route::delete('/logs/purge/all', [LogController::class, 'purgeAll'])->name('logs.purge.all');
        });
    });
});