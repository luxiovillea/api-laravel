<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsController;

// RUTE BARU UNTUK DASHBOARD SUMMARY
Route::get('/analytics/dashboard-summary', [AnalyticsController::class, 'getDashboardSummary']);


// RUTE LAMA ANDA (BISA DIPERTAHANKAN ATAU DIHAPUS JIKA TIDAK PERLU)
Route::get('/analytics-data', [AnalyticsController::class, 'fetchRealtimeData']);
Route::get('/analytics-historical', [AnalyticsController::class, 'fetchHistoricalData']);
Route::get('/analytics/pages-report', [AnalyticsController::class, 'fetchPagesReport']);
Route::get('/analytics/geography-report', [AnalyticsController::class, 'fetchGeographyReport']);

// Rute untuk laporan detail per aplikasi
Route::get('/analytics/{app_key}/report', [AnalyticsController::class, 'generateReport']);


// Route default untuk user 
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});