<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// HANYA ADA SATU RUTE INI:
// URL /api/analytics-data akan memanggil fungsi fetchHistoricalData.
Route::get('/analytics-data', [AnalyticsController::class, 'fetchHistoricalData']);


// Rute default Laravel (biarkan saja)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});