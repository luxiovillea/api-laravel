<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsController; // Pastikan use statement ini ada di atas


// Ini route bawaan, bisa Anda hapus atau biarkan saja
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


// == KODE ANDA DITARUH DI SINI ==
Route::get('/analytics-data', [AnalyticsController::class, 'fetchRealtimeData']);
Route::get('/analytics-historical', [AnalyticsController::class, 'fetchHistoricalData']);