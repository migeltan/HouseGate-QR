<?php

use App\Http\Controllers\LogController;
use App\Http\Controllers\PassController;
use App\Http\Controllers\ScannerController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ScannerController::class, 'index'])->name('scanner.index');
Route::post('/scan', [ScannerController::class, 'scan'])->name('scanner.scan');

Route::get('/passes', [PassController::class, 'index'])->name('passes.index');
Route::post('/passes/register', [PassController::class, 'register'])->name('passes.register');
Route::get('/passes/{pass}', [PassController::class, 'show'])->name('passes.show');

Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
Route::get('/logs/export', [LogController::class, 'export'])->name('logs.export');

// NEW: purge/delete endpoints. No auth middleware added — none exists on the
// routes above either, so add ->middleware(...) here once you tell me what
// your auth/role setup looks like.
Route::delete('/logs/purge/range', [LogController::class, 'purgeRange'])->name('logs.purge.range');
Route::delete('/logs/purge/all', [LogController::class, 'purgeAll'])->name('logs.purge.all');

Route::put('/passes/{pass}/buildings', [PassController::class, 'updateBuildings'])->name('passes.buildings.update');
Route::post('/passes/{pass}/unassign', [PassController::class, 'unassign'])->name('passes.unassign');

Route::get('/logs/registrations/export', [LogController::class, 'exportRegistrations'])->name('logs.registrations.export');