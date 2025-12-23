<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Твои маршруты
Route::prefix('v1')->group(function () {
    Route::post('/extract', [\App\Http\Controllers\ColorPaletteController::class, 'extractFromImage']);
    Route::post('/harmony', [\App\Http\Controllers\ColorPaletteController::class, 'generateHarmony']);
    Route::post('/meaning', [\App\Http\Controllers\ColorPaletteController::class, 'getColorMeaning']);
    Route::post('/contrast', [\App\Http\Controllers\ColorPaletteController::class, 'checkContrast']);
    Route::post('/export', [\App\Http\Controllers\ColorPaletteController::class, 'exportPalette']);
});
