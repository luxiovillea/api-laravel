<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Api\AplikasiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Berikut adalah route untuk API Google Analytics dan Management Aplikasi.
|
*/

// ===================================================================
// ROUTES UNTUK MANAGEMENT APLIKASI
// ===================================================================
Route::apiResource('/aplikasi', AplikasiController::class);
Route::get('/aplikasi/key/{key}', [AplikasiController::class, 'getByKey'])->name('aplikasi.getByKey');

// ===================================================================
// ROUTES ANALYTICS - ENDPOINT UTAMA (DIREKOMENDASIKAN)
// ===================================================================
// Dashboard Summary - Sekarang dinamis berdasarkan database
Route::get('/analytics/dashboard-summary', [AnalyticsController::class, 'getDashboardSummary']);

// Realtime Summary - Sekarang dinamis berdasarkan database  
Route::get('/analytics/realtime-summary', [AnalyticsController::class, 'getRealtimeSummary']);

// Detail Report per Aplikasi - Sekarang menggunakan data dari database
Route::get('/analytics/{app_key}/report', [AnalyticsController::class, 'generateReport'])->name('analytics.detail.report');

// ===================================================================
// ROUTES ANALYTICS - ENDPOINT LEGACY (UNTUK KOMPATIBILITAS)
// ===================================================================
// Sekarang mendukung parameter app_key untuk menggunakan aplikasi dari database
Route::get('/analytics-data', [AnalyticsController::class, 'fetchRealtimeData']);
Route::get('/analytics-historical', [AnalyticsController::class, 'fetchHistoricalData']);
Route::get('/analytics/pages-report', [AnalyticsController::class, 'fetchPagesReport']);
Route::get('/analytics/geography-report', [AnalyticsController::class, 'fetchGeographyReport']);

// ===================================================================
// ROUTE OTENTIKASI DEFAULT
// ===================================================================
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});