<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BuildingSelectController;
use App\Http\Controllers\CongressmanController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\PassController;
use App\Http\Controllers\PassInventoryController;
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
        // Must stay ABOVE /passes/{pass}, otherwise {pass} swallows these fixed segments.
        Route::get('/passes/check-duplicate', [PassController::class, 'checkDuplicate'])->name('passes.check-duplicate');
        Route::get('/passes/lookup-transfer-source', [PassController::class, 'lookupTransferSource'])->name('passes.lookup-transfer-source');
        Route::get('/passes/building/{building}/rows', [PassController::class, 'buildingRows'])->name('passes.building.rows');
        Route::post('/passes/register', [PassController::class, 'register'])->name('passes.register');
        Route::get('/passes/{pass}', [PassController::class, 'show'])->name('passes.show');
        Route::put('/passes/{pass}/buildings', [PassController::class, 'updateBuildings'])->name('passes.buildings.update');
        Route::post('/passes/{pass}/unassign', [PassController::class, 'unassign'])->name('passes.unassign');
        Route::post('/passes/{pass}/revoke', [PassController::class, 'revoke'])->name('passes.revoke');
        Route::get('/account', [AccountController::class, 'show'])->name('account.edit');
        Route::get('/account/log', [AccountController::class, 'log'])->middleware('admin')->name('account.log');
        Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile');
        Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');

        Route::get('/directory', [CongressmanController::class, 'index'])->name('congressmen.index');
        Route::get('/directory/roster', [CongressmanController::class, 'roster'])->name('congressmen.roster');

        Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
        Route::get('/logs/export', [LogController::class, 'export'])->name('logs.export');
        Route::get('/logs/registrations/export', [LogController::class, 'exportRegistrations'])->name('logs.registrations.export');

        // Admin-only — guards get a 403 even if they hit these URLs directly.
        Route::middleware('admin')->group(function () {
            Route::delete('/logs/purge/range', [LogController::class, 'purgeRange'])->name('logs.purge.range');
            Route::delete('/logs/purge/all', [LogController::class, 'purgeAll'])->name('logs.purge.all');

            Route::get('/account/users', [AccountController::class, 'users'])->name('account.users');
            Route::post('/account/users', [AccountController::class, 'storeUser'])->name('account.users.store');
            Route::put('/account/users/{user}', [AccountController::class, 'updateUser'])->name('account.users.update');
            Route::put('/account/users/{user}/password', [AccountController::class, 'resetPassword'])->name('account.users.password');
            Route::post('/account/users/{user}/deactivate', [AccountController::class, 'deactivate'])->name('account.users.deactivate');
            Route::post('/account/users/{user}/reactivate', [AccountController::class, 'reactivate'])->name('account.users.reactivate');

            Route::post('/directory', [CongressmanController::class, 'store'])->name('congressmen.store');
            Route::put('/directory/{congressman}', [CongressmanController::class, 'update'])->name('congressmen.update');
            Route::post('/directory/{congressman}/deactivate', [CongressmanController::class, 'deactivate'])->name('congressmen.deactivate');
            Route::post('/directory/{congressman}/reactivate', [CongressmanController::class, 'reactivate'])->name('congressmen.reactivate');

            Route::post('/passes/inventory/generate', [PassInventoryController::class, 'generate'])->name('passes.inventory.generate');
            Route::get('/passes/inventory/print', [PassInventoryController::class, 'print'])->name('passes.inventory.print');
            Route::get('/passes/inventory/csv', [PassInventoryController::class, 'csv'])->name('passes.inventory.csv');
        });
    });
});