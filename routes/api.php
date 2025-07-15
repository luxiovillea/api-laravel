<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Hapus atau komentari rute lama
// Route::get('/analytics/historical', [AnalyticsController::class, 'fetchHistoricalData']);
// Route::get('/analytics/realtime', [AnalyticsController::class, 'fetchRealtimeData']);

// RUTE BARU: Menambahkan {propertyId} sebagai parameter wajib
Route::get('/analytics/historical/{propertyId}', [AnalyticsController::class, 'fetchHistoricalData']);
Route::get('/analytics/realtime/{propertyId}', [AnalyticsController::class, 'fetchRealtimeData']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});